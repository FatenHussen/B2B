<?php

declare(strict_types=1);

namespace Modules\Reference\Infrastructure;

use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Enums\ZoneStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\Equipment;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Domain\Models\Zone;

final class EloquentReferenceDirectory implements ReferenceDirectory
{
    public function zoneBelongsToGovernorate(int $zoneId, int $governorateId): bool
    {
        return Zone::query()
            ->whereKey($zoneId)
            ->where('governorate_id', $governorateId)
            ->exists();
    }

    public function zoneIsActive(int $zoneId): bool
    {
        return Zone::query()
            ->whereKey($zoneId)
            ->where('status', ZoneStatus::Active)
            ->exists();
    }

    public function activityTypeExists(int $activityTypeId): bool
    {
        return ActivityType::query()
            ->whereKey($activityTypeId)
            ->where('status', RefStatus::Active)
            ->exists();
    }

    public function equipmentExists(int $equipmentId): bool
    {
        return Equipment::query()->whereKey($equipmentId)->exists();
    }

    public function rootCategoryExists(int $categoryId): bool
    {
        return RootCategory::query()->whereKey($categoryId)->exists();
    }

    public function allEquipmentsExist(array $ids): bool
    {
        if ($ids === []) {
            return true;
        }

        return Equipment::query()->whereIn('id', $ids)->count() === count(array_unique($ids));
    }

    public function allRootCategoriesExist(array $ids): bool
    {
        if ($ids === []) {
            return true;
        }

        return RootCategory::query()->whereIn('id', $ids)->count() === count(array_unique($ids));
    }

    public function saleUnitExists(int $saleUnitId): bool
    {
        return SaleUnit::query()
            ->whereKey($saleUnitId)
            ->where('status', RefStatus::Active)
            ->exists();
    }

    public function saleUnitName(int $saleUnitId): ?string
    {
        $name = SaleUnit::query()->whereKey($saleUnitId)->value('name');

        return is_string($name) ? $name : null;
    }

    public function currencyExists(int $currencyId): bool
    {
        return Currency::query()
            ->whereKey($currencyId)
            ->where('status', RefStatus::Active)
            ->exists();
    }

    public function currencyCode(int $currencyId): ?string
    {
        $code = Currency::query()->whereKey($currencyId)->value('code');

        return is_string($code) ? $code : null;
    }

    public function defaultCurrencyId(): ?int
    {
        $id = Currency::query()->where('is_base', true)->value('id')
            ?? Currency::query()->where('code', 'SYP')->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function allActivityTypesExist(array $ids): bool
    {
        if ($ids === []) {
            return true;
        }

        return ActivityType::query()->whereIn('id', $ids)->count() === count(array_unique($ids));
    }

    public function allZonesExist(array $ids): bool
    {
        if ($ids === []) {
            return true;
        }

        return Zone::query()->whereIn('id', $ids)->count() === count(array_unique($ids));
    }

    public function activityTypeName(int $activityTypeId): ?string
    {
        $name = ActivityType::query()->whereKey($activityTypeId)->value('name');

        return is_string($name) ? $name : null;
    }

    public function zoneName(int $zoneId): ?string
    {
        $name = Zone::query()->whereKey($zoneId)->value('name');

        return is_string($name) ? $name : null;
    }

    public function zoneGovernorateId(int $zoneId): ?int
    {
        $id = Zone::query()->whereKey($zoneId)->value('governorate_id');

        return $id !== null ? (int) $id : null;
    }

    public function rootCategoryName(int $rootCategoryId): ?string
    {
        $name = RootCategory::query()->whereKey($rootCategoryId)->value('name');

        return is_string($name) ? $name : null;
    }

    public function activeRootCategories(): array
    {
        return RootCategory::query()
            ->where('status', RefStatus::Active)
            ->orderBy('order')
            ->get()
            ->map(fn (RootCategory $row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'image' => $row->image,
                'icon' => $row->icon,
                'order' => (int) $row->order,
            ])
            ->all();
    }
}
