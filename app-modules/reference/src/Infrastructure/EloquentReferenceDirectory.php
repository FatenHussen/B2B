<?php

declare(strict_types=1);

namespace Modules\Reference\Infrastructure;

use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Enums\ZoneStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Equipment;
use Modules\Reference\Domain\Models\RootCategory;
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
}
