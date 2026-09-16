<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure;

use Modules\Core\Contracts\RepCommercialLimits;
use Modules\Pricing\Domain\Models\RepCommercialLimit;

final class EloquentRepCommercialLimits implements RepCommercialLimits
{
    public function maxDiscountPercent(int $channelId, int $repId): int
    {
        // acrossChannels(), per rule 10 (BE-C12): the caller is `/app/rep/*`, which sets no
        // tenant — a rep submitting a section — and names the channel explicitly. The
        // `channel_id` below is that argument, so the escape widens nothing: the scope
        // would have applied exactly this filter had a tenant been set.
        return (int) RepCommercialLimit::query()
            ->acrossChannels()
            ->where('channel_id', $channelId)
            ->where('rep_id', $repId)
            ->value('max_discount_percent');
    }
}
