<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure;

use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Contracts\AvailabilityClassifier;
use Modules\Core\Support\Tenant;

final class ActiveProductAvailabilityClassifier implements AvailabilityClassifier
{
    public function classify(int $productId): string
    {
        $active = Tenant::withoutScope(fn () => Product::query()
            ->whereKey($productId)
            ->where('status', ProductStatus::Active)
            ->exists());

        return $active ? 'in_stock' : 'out_of_stock';
    }
}
