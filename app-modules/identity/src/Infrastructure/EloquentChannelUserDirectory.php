<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\ChannelUserDirectory;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\ChannelUserChannel;

final class EloquentChannelUserDirectory implements ChannelUserDirectory
{
    public function listForChannel(int $channelId): array
    {
        // Relaxed ChannelUserChannel: filter by owner channel_id, no tenant required on /platform.
        $userIds = ChannelUserChannel::query()
            ->where('channel_id', $channelId)
            ->pluck('channel_user_id');

        return ChannelUser::query()
            ->whereIn('id', $userIds)
            ->orderBy('id')
            ->get()
            ->map(function (ChannelUser $user): array {
                $role = $user->getRoleNames()->first();

                return [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'phone' => $user->phone,
                    'role' => $role !== null ? (string) $role : null,
                    'status' => $user->status->value,
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                ];
            })
            ->all();
    }
}
