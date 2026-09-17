<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\ChannelUserCounter;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUserChannel;

final class EloquentChannelUserCounter implements ChannelUserCounter
{
    public function countInChannel(int $channelId): int
    {
        // Counted as that channel: the platform caller may carry another tenant in
        // X-Channel-Id, and `ChannelUserChannel`'s relaxed scope would honour it.
        return Tenant::as($channelId, fn (): int => ChannelUserChannel::query()
            ->where('channel_id', $channelId)
            ->distinct('channel_user_id')
            ->count('channel_user_id'));
    }
}
