<?php

declare(strict_types=1);

namespace Modules\Tenancy\Infrastructure;

use Modules\Core\Contracts\ChannelLimits;

final class StubChannelLimits implements ChannelLimits
{
    public function skuCap(int $channelId): int
    {
        return 5000;
    }
}
