<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Application\Support\WalletLedger;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\WalletTransaction;

final class ShowChannelRepWallet
{
    public function __construct(
        private readonly RepDirectory $reps,
        private readonly WalletLedger $wallet,
    ) {}

    /**
     * @return array{
     *     net_balance: int,
     *     stats: array{invoices_delivered: int, collected_total: int, receivables: int},
     *     today: array{invoices: int, collected: int, receivables: int}
     * }
     */
    public function __invoke(int $repUserId): array
    {
        $channelId = (int) Tenant::currentId();
        if (! $this->reps->belongsToChannel($repUserId, $channelId)) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        return Tenant::as($channelId, function () use ($repUserId): array {
            $today = now('Asia/Damascus')->toDateString();
            $invoices = Invoice::query()->where('rep_id', $repUserId)->get();
            $todayInvoices = $invoices->filter(
                fn (Invoice $invoice) => $invoice->created_at?->timezone('Asia/Damascus')->toDateString() === $today
            );

            $collectedTotal = (int) WalletTransaction::query()
                ->where('rep_id', $repUserId)
                ->where('type', 'collected')
                ->sum('amount');
            $todayStart = now('Asia/Damascus')->startOfDay()->timezone('UTC');
            $todayEnd = now('Asia/Damascus')->endOfDay()->timezone('UTC');
            $todayCollected = (int) WalletTransaction::query()
                ->where('rep_id', $repUserId)
                ->where('type', 'collected')
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->sum('amount');

            return [
                'net_balance' => $this->wallet->balance($repUserId),
                'stats' => [
                    'invoices_delivered' => $invoices->count(),
                    'collected_total' => $collectedTotal,
                    'receivables' => (int) $invoices->sum(fn (Invoice $invoice) => max(0, $invoice->remaining())),
                ],
                'today' => [
                    'invoices' => $todayInvoices->count(),
                    'collected' => $todayCollected,
                    'receivables' => (int) $todayInvoices->sum(fn (Invoice $invoice) => max(0, $invoice->remaining())),
                ],
            ];
        });
    }
}
