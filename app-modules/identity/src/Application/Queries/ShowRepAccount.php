<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Illuminate\Support\Carbon;
use Modules\Core\Contracts\RepMonthOutcomes;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepSourcedShop;

final class ShowRepAccount
{
    public function __construct(
        private readonly RepSellingContext $selling,
        private readonly RepMonthOutcomes $outcomes,
    ) {}

    /**
     * @return array{month: string, orders_completed: int, orders_undelivered: int, customers_added: int, execution_rate: int|null}
     */
    public function __invoke(AppUser $user, ?string $month): array
    {
        $month = $this->month($month);
        $ctx = $this->selling->for($user);
        $parsed = Carbon::createFromFormat('Y-m', $month, 'Asia/Damascus');
        $start = $parsed instanceof Carbon
            ? $parsed->copy()->startOfMonth()
            : now('Asia/Damascus')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $added = RepSourcedShop::query()
            ->where('rep_id', $ctx['rep_id'])
            ->whereBetween('created_at', [$start->copy()->utc(), $end->copy()->utc()])
            ->count();
        $counts = $this->outcomes->inMonth((int) $user->getAuthIdentifier(), $month);
        $closed = $counts['completed'] + $counts['undelivered'];

        return [
            'month' => $month,
            'orders_completed' => $counts['completed'],
            'orders_undelivered' => $counts['undelivered'],
            'customers_added' => $added,
            'execution_rate' => $closed === 0 ? null : intdiv($counts['completed'] * 100, $closed),
        ];
    }

    private function month(?string $month): string
    {
        if ($month === null || $month === '') {
            return now('Asia/Damascus')->format('Y-m');
        }

        return $month;
    }
}
