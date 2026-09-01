<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure;

use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Contracts\AvailabilityClassifier;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\Tenant;

final class EloquentCatalogProductLookup implements CatalogProductLookup
{
    public function __construct(private readonly ReferenceDirectory $refs) {}

    public function exists(int $productId): bool
    {
        return Tenant::withoutScope(fn () => Product::query()->whereKey($productId)->exists());
    }

    public function isActive(int $productId): bool
    {
        return Tenant::withoutScope(fn () => Product::query()
            ->whereKey($productId)
            ->where('status', ProductStatus::Active)
            ->exists());
    }

    public function channelId(int $productId): ?int
    {
        $id = Tenant::withoutScope(fn () => Product::query()->whereKey($productId)->value('supply_channel_id'));

        return $id !== null ? (int) $id : null;
    }

    public function name(int $productId): ?string
    {
        $name = Tenant::withoutScope(fn () => Product::query()->whereKey($productId)->value('name_ar'));

        return is_string($name) ? $name : null;
    }

    public function saleUnitName(int $productId): ?string
    {
        $unitId = Tenant::withoutScope(fn () => Product::query()->whereKey($productId)->value('sale_unit_id'));

        return $unitId !== null ? $this->refs->saleUnitName((int) $unitId) : null;
    }

    public function isVisibleToRetailer(int $productId, array $shopping): bool
    {
        $channelIds = $shopping['channel_ids'] ?? [];
        if ($channelIds === []) {
            return false;
        }

        return Tenant::withoutScope(fn () => Product::query()
            ->whereKey($productId)
            ->where('status', ProductStatus::Active)
            ->whereIn('supply_channel_id', $channelIds)
            ->whereHas('zones', fn ($q) => $q->where('zone_id', $shopping['zone_id']))
            ->whereHas('activityTypes', fn ($q) => $q->where('activity_type_id', $shopping['activity_type_id']))
            ->exists());
    }
}
