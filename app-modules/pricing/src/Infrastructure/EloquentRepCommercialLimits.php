<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure;

use Modules\Core\Contracts\RepCommercialLimits;
use Modules\Pricing\Domain\Models\RepCommercialLimit;

final class EloquentRepCommercialLimits implements RepCommercialLimits
{
    public function maxDiscountPercent(int $channelId, int $repId): int
    {
        $row = $this->row($channelId, $repId);

        return $row === null ? 0 : (int) $row->max_discount_percent;
    }

    public function maxCashHold(int $channelId, int $repId): int
    {
        $row = $this->row($channelId, $repId);

        return $row === null ? 0 : (int) $row->max_cash_hold;
    }

    private function row(int $channelId, int $repId): ?RepCommercialLimit
    {
        // acrossChannels(), per rule 10 (BE-C12): the caller is `/app/rep/*`, which sets no
        // tenant — a rep submitting a section or collecting cash — and names the channel
        // explicitly. The `channel_id` below is that argument, so the escape widens
        // nothing: the scope would have applied exactly this filter had a tenant been set.
        return RepCommercialLimit::query()
            ->acrossChannels()
            ->where('channel_id', $channelId)
            ->where('rep_id', $repId)
            ->first();
    }
}
