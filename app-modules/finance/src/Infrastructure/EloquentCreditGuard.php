<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Modules\Core\Contracts\CreditGuard;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\RetailerCreditLimit;

final class EloquentCreditGuard implements CreditGuard
{
    public function assertWithinLimit(int $retailerId, int $channelId, int $amountMinor): void
    {
        Tenant::as($channelId, function () use ($retailerId, $channelId, $amountMinor): void {
            $limit = RetailerCreditLimit::query()
                ->where('retailer_id', $retailerId)
                ->first();

            if ($limit === null || $limit->on_exceed === 'warn') {
                return;
            }

            $outstanding = (int) Invoice::query()
                ->where('retailer_id', $retailerId)
                ->where('status', 'open')
                ->get()
                ->sum(fn (Invoice $invoice) => $invoice->remaining());

            if ($outstanding + $amountMinor <= (int) $limit->credit_limit) {
                return;
            }

            throw new DomainException(
                __('finance.credit_limit_exceeded'),
                'credit_limit_exceeded',
                423,
                ['retailer_id' => $retailerId, 'channel_id' => $channelId],
            );
        });
    }
}
