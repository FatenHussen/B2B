<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Modules\Catalog\Application\Support\CatalogBarcode;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class UpdateVariant
{
    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly RecordsAudit $audit,
        private readonly StockLedger $stock,
        private readonly WarehouseDirectory $warehouses,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function __invoke(object $actor, int $productId, int $variantId, array $data): array
    {
        $product = Product::query()->findOrFail($productId);
        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereKey($variantId)
            ->first();

        if ($variant === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        if (isset($data['sku'])) {
            $sku = (string) $data['sku'];
            $taken = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('sku', $sku)
                ->where('id', '!=', $variant->id)
                ->exists();
            if ($taken) {
                InvalidFields::throw(['sku' => 'catalog.variant_sku_taken']);
            }
            $variant->sku = $sku;
        }

        if (array_key_exists('barcode', $data)) {
            $barcode = $data['barcode'] !== null ? trim((string) $data['barcode']) : '';
            if ($barcode !== '') {
                CatalogBarcode::assertUnique($barcode, null, (int) $variant->id);
            }
            $variant->barcode = $barcode !== '' ? $barcode : null;
        }

        if (array_key_exists('image', $data)) {
            $image = $data['image'];
            $variant->image_media_id = $image !== null && $image !== '' ? (int) $image : null;
        }

        if (isset($data['status'])) {
            $variant->status = (string) $data['status'];
        }

        if (array_key_exists('price_override', $data)) {
            $variant->price_override = $data['price_override'] !== null ? (int) $data['price_override'] : null;
        }

        $variant->save();

        $stock = 0;
        $hasStock = array_key_exists('stock', $data);
        $hasWarehouse = array_key_exists('warehouse_id', $data);
        if ($hasStock xor $hasWarehouse) {
            InvalidFields::throw([
                'stock' => 'catalog.variant_stock_pair',
                'warehouse_id' => 'catalog.variant_stock_pair',
            ]);
        }
        if ($hasStock && $hasWarehouse) {
            $warehouseId = (int) $data['warehouse_id'];
            $channelId = (int) Tenant::currentId();
            if (! $this->warehouses->belongsToChannel($warehouseId, $channelId)) {
                InvalidFields::throw(['warehouse_id' => 'catalog.warehouse_not_found']);
            }
            $target = (int) $data['stock'];
            $current = $this->stock->snapshot($warehouseId, (int) $product->id, (int) $variant->id)['on_hand'];
            $delta = $target - $current;
            if ($delta !== 0) {
                $this->stock->adjust(
                    $warehouseId,
                    (int) $product->id,
                    (int) $variant->id,
                    $delta,
                    'variant_set_stock',
                    $actor,
                );
            }
            $stock = $target;
        } else {
            $defaultWarehouse = $this->warehouses->defaultIdForChannel((int) Tenant::currentId());
            if ($defaultWarehouse !== null) {
                $stock = $this->stock->snapshot($defaultWarehouse, (int) $product->id, (int) $variant->id)['on_hand'];
            }
        }

        $this->audit->record('catalog.variant.update', $actor, 'product_variant', (int) $variant->id, [
            'after' => ['sku' => $variant->sku, 'price_override' => $variant->price_override],
        ], Tenant::currentId());

        return $this->present($product, $variant, $stock);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Product $product, ProductVariant $variant, int $stock): array
    {
        $quoted = $this->pricing->quoteLine(
            (int) $product->id,
            1,
            0,
            null,
            (int) $product->supply_channel_id,
            [],
            (int) $variant->id,
        );

        return [
            'id' => (int) $variant->id,
            'sku' => (string) $variant->sku,
            'combination' => $variant->combination,
            'price' => $quoted['unit_price'],
            'stock' => $stock,
            'barcode' => $variant->barcode,
            'image' => $variant->image_media_id !== null ? (string) $variant->image_media_id : null,
            'status' => (string) $variant->status,
            'price_override' => $variant->price_override !== null ? (int) $variant->price_override : null,
        ];
    }
}
