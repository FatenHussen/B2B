<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\RetailerGroupDirectory;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Identity\Domain\Models\RetailerProfileCategory;
use Modules\Identity\Domain\Models\RetailerProfileEquipment;

final class EloquentRetailerDirectory implements RetailerDirectory
{
    public function __construct(private readonly RetailerGroupDirectory $groups) {}

    public function find(int $retailerId): ?array
    {
        $profile = RetailerProfile::query()->find($retailerId);

        if ($profile === null) {
            return null;
        }

        return [
            'id' => (int) $profile->getKey(),
            'shop_name' => (string) $profile->getAttribute('shop_name'),
            'address' => $profile->getAttribute('address'),
        ];
    }

    public function countInZone(int $zoneId): int
    {
        return RetailerProfile::query()->where('zone_id', $zoneId)->count();
    }

    public function appUserIdsInZones(array $zoneIds): array
    {
        if ($zoneIds === []) {
            return [];
        }

        return RetailerProfile::query()
            ->whereIn('zone_id', $zoneIds)
            ->whereNotNull('app_user_id')
            ->pluck('app_user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function appUserIdsForProfiles(array $retailerProfileIds): array
    {
        if ($retailerProfileIds === []) {
            return [];
        }

        return RetailerProfile::query()
            ->whereKey($retailerProfileIds)
            ->whereNotNull('app_user_id')
            ->pluck('app_user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function appUserIdsByActivityTypes(array $activityTypeIds): array
    {
        if ($activityTypeIds === []) {
            return [];
        }

        return RetailerProfile::query()
            ->whereIn('activity_type_id', $activityTypeIds)
            ->whereNotNull('app_user_id')
            ->pluck('app_user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function allAppUserIds(): array
    {
        return RetailerProfile::query()
            ->whereNotNull('app_user_id')
            ->pluck('app_user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function exists(int $retailerId): bool
    {
        return RetailerProfile::query()->whereKey($retailerId)->exists();
    }

    public function phone(int $retailerId): ?string
    {
        $ownerId = $this->appUserId($retailerId);

        if ($ownerId === null) {
            return null;
        }

        $phone = AppUser::query()->whereKey($ownerId)->value('phone');

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    public function appUserId(int $retailerId): ?int
    {
        $ownerId = RetailerProfile::query()->whereKey($retailerId)->value('app_user_id');

        return $ownerId === null ? null : (int) $ownerId;
    }

    public function profileIdForUser(int $appUserId): ?int
    {
        $id = RetailerProfile::query()->where('app_user_id', $appUserId)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function zoneId(int $retailerId): ?int
    {
        $zoneId = RetailerProfile::query()->whereKey($retailerId)->value('zone_id');

        return $zoneId === null ? null : (int) $zoneId;
    }

    public function activityTypeId(int $retailerId): ?int
    {
        $id = RetailerProfile::query()->whereKey($retailerId)->value('activity_type_id');

        return $id === null ? null : (int) $id;
    }

    public function groupIds(int $retailerId): array
    {
        return $this->groups->groupIdsForRetailer($retailerId);
    }

    public function countByActivityType(int $activityTypeId): int
    {
        return RetailerProfile::query()->where('activity_type_id', $activityTypeId)->count();
    }

    public function countInGovernorate(int $governorateId): int
    {
        return RetailerProfile::query()->where('governorate_id', $governorateId)->count();
    }

    public function countByEquipment(int $equipmentId): int
    {
        return RetailerProfileEquipment::query()
            ->where('equipment_id', $equipmentId)
            ->distinct('retailer_profile_id')
            ->count('retailer_profile_id');
    }

    public function countByRootCategory(int $rootCategoryId): int
    {
        return RetailerProfileCategory::query()
            ->where('root_category_id', $rootCategoryId)
            ->distinct('retailer_profile_id')
            ->count('retailer_profile_id');
    }
}
