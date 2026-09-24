<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\AssignsChannelManager;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\ChannelUserChannel;

final class EloquentAssignsChannelManager implements AssignsChannelManager
{
    public function assign(int $channelId, int $channelUserId): void
    {
        $user = ChannelUser::query()->find($channelUserId);
        if ($user === null) {
            return;
        }

        $exists = ChannelUserChannel::query()
            ->where('channel_user_id', $channelUserId)
            ->where('channel_id', $channelId)
            ->exists();

        if (! $exists) {
            $hasOther = ChannelUserChannel::query()
                ->where('channel_user_id', $channelUserId)
                ->exists();

            ChannelUserChannel::query()->create([
                'channel_user_id' => $channelUserId,
                'channel_id' => $channelId,
                'is_default' => ! $hasOther,
            ]);
        }

        if (! $user->hasRole('channel_manager')) {
            $user->assignRole('channel_manager');
        }
    }
}
