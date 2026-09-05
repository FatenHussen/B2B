<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\RepDirectory;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;

final class EloquentRepDirectory implements RepDirectory
{
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
}
