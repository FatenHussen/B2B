<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Invoice;

final class ListRepReceivables
{
    public function __construct(
        private readonly RepDirectory $reps,
        private readonly RetailerDirectory $retailers,
        private readonly ChannelDirectory $channels,
        private readonly ReferenceDirectory $refs,
    ) {}

    /**
     * @return array{total: int, by_shop: list<array<string, mixed>>}
     */
    public function __invoke(object $user): array
    {
        $repUserId = (int) $user->getAuthIdentifier();
        $channelId = $this->reps->channelIdForUser($repUserId);
        if ($channelId === null) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        return Tenant::as($channelId, function () use ($repUserId): array {
            $open = Invoice::query()
                ->where('rep_id', $repUserId)
                ->orderBy('id')
                ->get()
                ->filter(fn (Invoice $invoice) => $invoice->remaining() > 0)
                ->groupBy(fn (Invoice $invoice) => (int) $invoice->retailer_id);

            $byShop = [];
            foreach ($open as $retailerId => $invoices) {
                $shop = $this->retailers->find((int) $retailerId);
                $zoneId = $this->retailers->zoneId((int) $retailerId);
                $first = $invoices->first();
                $lines = $invoices->map(fn (Invoice $invoice): array => [
                    'no' => (string) $invoice->no,
                    'total' => (int) $invoice->total,
                    'paid' => (int) $invoice->paid_total,
                    'remaining' => $invoice->remaining(),
                    'created_at' => $invoice->created_at?->timezone('Asia/Damascus')->toIso8601String(),
                ])->values()->all();
                $byShop[] = [
                    'retailer_id' => (int) $retailerId,
                    'shop' => $shop['shop_name'] ?? null,
                    'zone' => $zoneId !== null ? $this->refs->zoneName($zoneId) : null,
                    'channel' => $first instanceof Invoice ? $this->channels->name((int) $first->supply_channel_id) : null,
                    'total' => (int) $invoices->sum(fn (Invoice $invoice) => $invoice->remaining()),
                    'invoices' => $lines,
                ];
            }

            return [
                'total' => (int) array_sum(array_column($byShop, 'total')),
                'by_shop' => $byShop,
            ];
        });
    }
}
