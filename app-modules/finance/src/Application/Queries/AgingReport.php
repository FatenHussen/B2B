<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Illuminate\Http\Request;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Finance\Domain\Models\Invoice;

final class AgingReport
{
    public function __construct(private readonly RetailerDirectory $retailers) {}

    /**
     * @return array{
     *     group_by: string,
     *     buckets: array{'0_30': int, '31_60': int, '61_90': int, over_90: int},
     *     groups: list<array{key: int|null, label: string|null, buckets: array{'0_30': int, '31_60': int, '61_90': int, over_90: int}}>
     * }
     */
    public function __invoke(Request $request): array
    {
        $groupBy = $request->query('group_by', 'zone');
        if (! in_array($groupBy, ['zone', 'rep'], true)) {
            $groupBy = 'zone';
        }

        $buckets = ['0_30' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0];
        /** @var array<string, array{'0_30': int, '31_60': int, '61_90': int, over_90: int}> $byGroup */
        $byGroup = [];
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

            $groupKey = $groupBy === 'rep'
                ? ($invoice->rep_id !== null ? (string) (int) $invoice->rep_id : '0')
                : (string) ($this->retailers->zoneId((int) $invoice->retailer_id) ?? 0);

            if (! isset($byGroup[$groupKey])) {
                $byGroup[$groupKey] = ['0_30' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0];
            }
            $byGroup[$groupKey][$key] += $remaining;
        }

        $groups = [];
        foreach ($byGroup as $gk => $groupBuckets) {
            $id = (int) $gk;
            $groups[] = [
                'key' => $id > 0 ? $id : null,
                'label' => $groupBy === 'zone' && $id > 0
                    ? null
                    : ($groupBy === 'rep' && $id > 0 ? (string) $id : null),
                'buckets' => $groupBuckets,
            ];
        }

        return [
            'group_by' => $groupBy,
            'buckets' => $buckets,
            'groups' => $groups,
        ];
    }
}
