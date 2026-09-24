<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RetailerGroupDirectory;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\RetailerGroup;
use Modules\Identity\Domain\Models\RetailerGroupMember;
use Modules\Identity\Domain\Models\RetailerProfile;

final class EloquentRetailerGroupDirectory implements RetailerGroupDirectory
{
    public function groupIdsForRetailer(int $retailerId): array
    {
        $ids = RetailerGroupMember::query()
            ->where('retailer_id', $retailerId)
            ->pluck('retailer_group_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique($ids));
    }

    public function allExistInChannel(int $channelId, array $groupIds): bool
    {
        if ($groupIds === []) {
            return true;
        }

        $unique = array_values(array_unique(array_map('intval', $groupIds)));

        return Tenant::as($channelId, function () use ($unique): bool {
            return RetailerGroup::query()->whereKey($unique)->count() === count($unique);
        });
    }

    public function existsInChannel(int $channelId, int $groupId): bool
    {
        return Tenant::as($channelId, fn () => RetailerGroup::query()->whereKey($groupId)->exists());
    }

    public function isInUse(int $groupId): bool
    {
        // Cross-module in-use check by identifier only (rule 3/4) — table names, no models.
        $inPriceLists = DB::table('price_lists')
            ->where('type', 'group')
            ->where('group_id', $groupId)
            ->exists();

        $inOffers = DB::table('offer_groups')
            ->where('group_id', $groupId)
            ->exists();

        return $inPriceLists || $inOffers;
    }

    public function appUserIdsInGroups(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $profileIds = RetailerGroupMember::query()
            ->whereIn('retailer_group_id', $groupIds)
            ->pluck('retailer_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if ($profileIds === []) {
            return [];
        }

        return RetailerProfile::query()
            ->whereKey($profileIds)
            ->whereNotNull('app_user_id')
            ->pluck('app_user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
