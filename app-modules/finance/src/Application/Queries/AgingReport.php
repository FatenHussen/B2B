<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Illuminate\Http\Request;
use Modules\Finance\Domain\Models\Invoice;

final class AgingReport
{
    /**
     * @return array{group_by: string, buckets: array{'0_30': int, '31_60': int, '61_90': int, over_90: int}}
     */
    public function __invoke(Request $request): array
    {
        $groupBy = $request->query('group_by', 'zone');
        if (! in_array($groupBy, ['zone', 'rep'], true)) {
            $groupBy = 'zone';
        }

        $buckets = ['0_30' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0];
        $now = now();

        foreach (Invoice::query()->where('status', 'open')->cursor() as $invoice) {
            $remaining = $invoice->remaining();
            if ($remaining <= 0) {
                continue;
            }

            $days = (int) ($invoice->created_at?->diffInDays($now) ?? 0);
            $key = match (true) {
                $days <= 30 => '0_30',
                $days <= 60 => '31_60',
                $days <= 90 => '61_90',
                default => 'over_90',
            };
            $buckets[$key] += $remaining;
        }

        return [
            'group_by' => $groupBy,
            'buckets' => $buckets,
        ];
    }
}
