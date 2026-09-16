<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Modules\Core\Contracts\ChannelFinanceMetrics;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\Payment;

final class EloquentChannelFinanceMetrics implements ChannelFinanceMetrics
{
    public function snapshot(int $channelId, string $onDate): array
    {
        return Tenant::as($channelId, function () use ($onDate): array {
            $cash = (int) Payment::query()->whereDate('created_at', $onDate)->sum('amount');
            $total = 0;
            $overdue = 0;
            $cutoff = now()->subDays(30);

            foreach (Invoice::query()->where('status', 'open')->cursor() as $invoice) {
                $remaining = $invoice->remaining();
                if ($remaining <= 0) {
                    continue;
                }
                $total += $remaining;
                if ($invoice->created_at !== null && $invoice->created_at->lt($cutoff)) {
                    $overdue += $remaining;
                }
            }

            return [
                'cash_collected' => $cash,
                'receivables' => ['total' => $total, 'overdue' => $overdue],
            ];
        });
    }
}
