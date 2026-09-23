<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Catalog\Domain\Models\ProductVariantAxis;
use Modules\Catalog\Domain\Models\ProductVariantAxisValue;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\Tenant;

final class GenerateVariants
{
    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{axes: list<array{name: string, values: list<string>}>}  $data
     * @return array{variants: list<array<string, mixed>>}
     */
    public function __invoke(object $actor, int $productId, array $data): array
    {
        $product = Product::query()->findOrFail($productId);
        $axes = $data['axes'] ?? [];

        $combinations = $this->cartesian($axes);
        $keepSkus = [];

        $variants = DB::transaction(function () use ($product, $axes, $combinations, &$keepSkus): array {
            $product->variantAxes()->each(function (ProductVariantAxis $axis): void {
                $axis->values()->delete();
                $axis->delete();
            });

            foreach ($axes as $i => $axis) {
                $row = ProductVariantAxis::query()->create([
                    'product_id' => $product->id,
                    'name' => $axis['name'],
                    'order' => $i,
                ]);
                foreach ($axis['values'] as $j => $value) {
                    ProductVariantAxisValue::query()->create([
                        'axis_id' => $row->id,
                        'value' => $value,
                        'order' => $j,
                    ]);
                }
            }

            $out = [];
            foreach ($combinations as $combo) {
                $sku = $product->sku.'-'.implode('-', array_map(
                    fn ($v) => preg_replace('/\s+/', '', (string) $v),
                    array_values($combo),
                ));
                $keepSkus[] = $sku;

                $variant = ProductVariant::query()->updateOrCreate(
                    ['product_id' => $product->id, 'sku' => $sku],
                    ['combination' => $combo, 'status' => 'active'],
                );
                $out[] = $variant;
            }

            ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereNotIn('sku', $keepSkus)
                ->delete();

            return $out;
        });

        $this->audit->record('catalog.variants.generate', $actor, 'product', (int) $product->id, [
            'after' => ['count' => count($variants)],
        ], Tenant::currentId());

        return [
            'variants' => array_map(function (ProductVariant $v) use ($product): array {
                $quoted = $this->pricing->quoteLine(
                    (int) $product->id,
                    1,
                    0,
                    null,
                    (int) $product->supply_channel_id,
                    [],
                    (int) $v->id,
                );

                return [
                    'id' => (int) $v->id,
                    'sku' => (string) $v->sku,
                    'combination' => $v->combination,
                    'price' => $quoted['unit_price'],
                    'stock' => 0,
                    'barcode' => $v->barcode,
                    'image' => $v->image_media_id !== null ? (string) $v->image_media_id : null,
                    'status' => (string) $v->status,
                    'price_override' => $v->price_override !== null ? (int) $v->price_override : null,
                ];
            }, $variants),
        ];
    }

    /**
     * @param  list<array{name: string, values: list<string>}>  $axes
     * @return list<array<string, string>>
     */
    private function cartesian(array $axes): array
    {
        $result = [[]];
        foreach ($axes as $axis) {
            $next = [];
            foreach ($result as $row) {
                foreach ($axis['values'] as $value) {
                    $next[] = $row + [$axis['name'] => $value];
                }
            }
            $result = $next;
        }

        return $result;
    }
}
