<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\RetailerCreditLimit;

final class ListRetailerDebts
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly ChannelDirectory $channels,
    ) {}

    /**
     * @return array{by_channel: list<array{channel: array{id: int, name: string|null}, total: int, overdue: int, invoices: list<array{no: string, total: int, remaining: int}>}>}
     */
    public function __invoke(object $user): array
    {
        if (! $this->shopping->isRetailer($user)) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $retailerId = (int) $this->shopping->for($user)['retailer_id'];

        // acrossChannels(), per rule 10: `/app/retailer/debts` sets no tenant; retailer_id owns the rows.
        $open = Invoice::query()
            ->acrossChannels()
            ->where('retailer_id', $retailerId)
            ->where('status', 'open')
            ->orderBy('id')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->remaining() > 0)
            ->groupBy(fn (Invoice $invoice) => (int) $invoice->supply_channel_id);

        $byChannel = [];
        foreach ($open as $channelId => $invoices) {
            $grace = $this->graceDays((int) $channelId, $retailerId);
            $lines = [];
            $total = 0;
            $overdue = 0;
            foreach ($invoices as $invoice) {
                $remaining = $invoice->remaining();
                $total += $remaining;
                $age = (int) ($invoice->created_at?->diffInDays(now()) ?? 0);
                if ($age > $grace) {
                    $overdue += $remaining;
                }
                $lines[] = [
                    'no' => (string) $invoice->no,
                    'total' => (int) $invoice->total,
                    'remaining' => $remaining,
                ];
            }
            $byChannel[] = [
                'channel' => [
                    'id' => (int) $channelId,
                    'name' => $this->channels->name((int) $channelId),
                ],
                'total' => $total,
                'overdue' => $overdue,
                'invoices' => $lines,
            ];
        }

        return ['by_channel' => $byChannel];
    }

    private function graceDays(int $channelId, int $retailerId): int
    {
        // acrossChannels(), per rule 10: credit limits are per channel; no tenant on /app/*.
        $grace = RetailerCreditLimit::query()
            ->acrossChannels()
            ->where('supply_channel_id', $channelId)
            ->where('retailer_id', $retailerId)
            ->value('grace_days');

        return $grace !== null ? (int) $grace : 30;
    }
}
