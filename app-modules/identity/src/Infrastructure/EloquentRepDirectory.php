<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\RepDirectory;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;

final class EloquentRepDirectory implements RepDirectory
{
    /**
     * Distinct rep profiles serving this zone.
     *
     * A rep serves many zones through the `rep_profile_zones` pivot, so this counts
     * profiles with at least one row for the zone — `whereHas`, not a join, which would
     * count pivot rows and inflate the number for any rep listed twice.
     */
    public function countInZone(int $zoneId): int
    {
        return RepProfile::query()
            ->whereHas('zones', fn ($q) => $q->where('zone_id', $zoneId))
            ->count();
    }

    public function exists(int $repUserId): bool
    {
        return AppUser::query()
            ->whereKey($repUserId)
            ->where('kind', AppUserKind::Rep)
            ->whereHas('repProfile')
            ->exists();
    }

    public function belongsToChannel(int $repUserId, int $channelId): bool
    {
        return AppUser::query()
            ->whereKey($repUserId)
            ->where('kind', AppUserKind::Rep)
            ->whereHas('repProfile', fn ($q) => $q->where('channel_id', $channelId))
            ->exists();
    }

    public function profileId(int $repUserId): ?int
    {
        $id = RepProfile::query()->where('app_user_id', $repUserId)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function userIdForProfile(int $profileId): ?int
    {
        $id = RepProfile::query()->whereKey($profileId)->value('app_user_id');

        return $id !== null ? (int) $id : null;
    }

    public function zoneIdsForUser(int $repUserId): array
    {
        $profile = RepProfile::query()->where('app_user_id', $repUserId)->first();

        return $profile?->zoneIds() ?? [];
    }

    public function channelIdForUser(int $repUserId): ?int
    {
        $id = RepProfile::query()->where('app_user_id', $repUserId)->value('channel_id');

        return $id !== null ? (int) $id : null;
    }

    public function displayName(int $repUserId): ?string
    {
        $name = AppUser::query()->whereKey($repUserId)->value('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function phone(int $repUserId): ?string
    {
        $phone = AppUser::query()->whereKey($repUserId)->value('phone');

        return is_string($phone) && $phone !== '' ? $phone : null;
    }
}
