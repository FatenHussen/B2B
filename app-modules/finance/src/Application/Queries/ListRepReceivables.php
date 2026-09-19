<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

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
    ) {}

    /**
     * @return array{by_shop: list<array{retailer_id: int, shop: string|null, total: int, invoices: list<array{no: string, total: int, paid: int, remaining: int}>}>}
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
                $lines = $invoices->map(fn (Invoice $invoice): array => [
                    'no' => (string) $invoice->no,
                    'total' => (int) $invoice->total,
                    'paid' => (int) $invoice->paid_total,
                    'remaining' => $invoice->remaining(),
                ])->values()->all();
                $byShop[] = [
                    'retailer_id' => (int) $retailerId,
                    'shop' => $shop['shop_name'] ?? null,
                    'total' => (int) $invoices->sum(fn (Invoice $invoice) => $invoice->remaining()),
                    'invoices' => $lines,
                ];
            }

            return ['by_shop' => $byShop];
        });
    }
}
