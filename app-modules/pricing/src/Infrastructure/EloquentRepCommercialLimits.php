<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure;

use Modules\Core\Contracts\RepCommercialLimits;
use Modules\Pricing\Domain\Models\RepCommercialLimit;

final class EloquentRepCommercialLimits implements RepCommercialLimits
{
    public function maxDiscountPercent(int $channelId, int $repId): int
    {
        return (int) RepCommercialLimit::query()
            ->where('channel_id', $channelId)
            ->where('rep_id', $repId)
            ->value('max_discount_percent');
    }
}
