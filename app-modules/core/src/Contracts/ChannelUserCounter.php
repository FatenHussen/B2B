<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * How many dashboard users a channel has — the `users` half of EP-AD-056 `limit_usage`
 * (BE-T11). Identity owns `channel_user_channels`; Tenancy only reads the number.
 */
interface ChannelUserCounter
{
    public function countInChannel(int $channelId): int;
}
