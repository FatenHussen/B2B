<?php

declare(strict_types=1);

namespace Modules\Inventory\Infrastructure;

use Modules\Core\Contracts\AvailabilityClassifier;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Support\Tenant;
use Modules\Inventory\Domain\Models\StockBalance;
use Modules\Inventory\Domain\Models\StockReorderPoint;

final class StockAvailabilityClassifier implements AvailabilityClassifier
{
    public function __construct(private readonly CatalogProductLookup $products) {}

    public function classify(int $productId): string
    {
        if (! $this->products->isActive($productId)) {
            return 'out_of_stock';
        }

        $snap = $this->products->snapshot($productId);
        if ($snap === null) {
            return 'out_of_stock';
        }

        if (! $snap['tracked']) {
            return 'in_stock';
        }

        $available = (int) Tenant::withoutScope(fn () => StockBalance::query()
            ->where('product_id', $productId)
            ->get()
            ->sum(fn (StockBalance $row) => $row->available()));

        if ($available <= 0) {
            return 'out_of_stock';
        }

        $point = (int) Tenant::withoutScope(fn () => StockReorderPoint::query()
            ->where('product_id', $productId)
            ->min('point')) ?: (int) $snap['reorder_point'];

        if ($point > 0 && $available < $point) {
            return 'low';
        }

        return 'in_stock';
    }
}
