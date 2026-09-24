<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\CreditGuard;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\CreditApprovalRequest;
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

            if ($limit->on_exceed === 'block') {
                throw new DomainException(
                    __('finance.credit_limit_exceeded'),
                    'credit_limit_exceeded',
                    423,
                    ['retailer_id' => $retailerId, 'channel_id' => $channelId],
                );
            }

            // manual_approval: consume an unused approved row that covers the overage, else queue.
            $overage = ($outstanding + $amountMinor) - (int) $limit->credit_limit;
            $creditLimit = (int) $limit->credit_limit;

            $consumed = DB::transaction(function () use ($retailerId, $overage): bool {
                $approved = CreditApprovalRequest::query()
                    ->where('retailer_id', $retailerId)
                    ->where('status', 'approved')
                    ->where('amount_minor', '>=', $overage)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($approved === null) {
                    return false;
                }

                $approved->forceFill([
                    'status' => 'consumed',
                    'consumed_at' => now(),
                ])->save();

                return true;
            });

            if ($consumed) {
                return;
            }

            $pending = CreditApprovalRequest::query()
                ->where('retailer_id', $retailerId)
                ->where('status', 'pending')
                ->orderBy('id')
                ->first();

            if ($pending === null) {
                $pending = new CreditApprovalRequest([
                    'retailer_id' => $retailerId,
                    'amount_minor' => $amountMinor,
                    'outstanding_at_request' => $outstanding,
                    'credit_limit_at_request' => $creditLimit,
                ]);
                $pending->forceFill(['status' => 'pending'])->save();
            } else {
                $pending->forceFill([
                    'amount_minor' => $amountMinor,
                    'outstanding_at_request' => $outstanding,
                    'credit_limit_at_request' => $creditLimit,
                ])->save();
            }

            throw new DomainException(
                __('finance.credit_limit_exceeded'),
                'credit_limit_exceeded',
                423,
                [
                    'approval_id' => (int) $pending->id,
                    'retailer_id' => $retailerId,
                    'channel_id' => $channelId,
                ],
            );
        });
    }
}
