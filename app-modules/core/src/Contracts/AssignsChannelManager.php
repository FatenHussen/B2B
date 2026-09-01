<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface AssignsChannelManager
{
    public function assign(int $channelId, int $channelUserId): void;
}
