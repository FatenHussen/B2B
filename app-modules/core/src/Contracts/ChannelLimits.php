<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ChannelLimits
{
    /** SKU cap for the channel. Default 5000 until SP-04 plans exist. */
    public function skuCap(int $channelId): int;
}
