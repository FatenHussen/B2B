<?php

declare(strict_types=1);

namespace Modules\Ordering\Infrastructure;

use Illuminate\Support\Carbon;
use Modules\Core\Contracts\RepMonthOutcomes;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;

final class EloquentRepMonthOutcomes implements RepMonthOutcomes
{
    public function inMonth(int $repUserId, string $yearMonth): array
    {
        $parsed = Carbon::createFromFormat('Y-m', $yearMonth, 'Asia/Damascus');
        if (! $parsed instanceof Carbon) {
            return ['completed' => 0, 'undelivered' => 0];
        }
        $start = $parsed->copy()->startOfMonth()->utc();
        $end = $parsed->copy()->endOfMonth()->utc();

        // acrossChannels(), per rule 10: the account sheet sets no tenant. rep_id
        // is the owner, so the count never crosses into another rep's orders.
        $base = SubOrder::query()
            ->acrossChannels()
            ->where('rep_id', $repUserId)
            ->whereBetween('created_at', [$start, $end]);

        return [
            'completed' => (clone $base)->where('status', SubOrderStatus::Delivered)->count(),
            'undelivered' => (clone $base)->where('status', SubOrderStatus::Undelivered)->count(),
        ];
    }
}
