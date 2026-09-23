<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Support;

use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Core\Support\InvalidFields;

final class CatalogBarcode
{
    public static function assertUnique(string $barcode, ?int $exceptProductId = null, ?int $exceptVariantId = null): void
    {
        if ($barcode === '') {
            return;
        }

        $productHit = Product::query()
            ->where('barcode', $barcode)
            ->when($exceptProductId, fn ($q) => $q->where('id', '!=', $exceptProductId))
            ->exists();
        if ($productHit) {
            InvalidFields::throw(['barcode' => 'catalog.barcode_taken']);
        }

        $variantHit = ProductVariant::query()
            ->where('barcode', $barcode)
            ->when($exceptVariantId, fn ($q) => $q->where('id', '!=', $exceptVariantId))
            ->whereHas('product')
            ->exists();
        if ($variantHit) {
            InvalidFields::throw(['barcode' => 'catalog.barcode_taken']);
        }
    }
}
