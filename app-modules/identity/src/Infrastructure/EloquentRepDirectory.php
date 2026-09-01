<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\RepDirectory;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Models\AppUser;

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
}
