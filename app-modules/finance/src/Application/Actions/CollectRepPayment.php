<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RepCommercialLimits;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Finance\Application\Support\WalletLedger;
use Modules\Finance\Domain\InvoiceLifecycle;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\Payment;
use Modules\Finance\Domain\Models\PaymentAllocation;
use Modules\Finance\Domain\Models\ReceiptReservation;
use Modules\Finance\Domain\Models\WalletTransaction;

final class CollectRepPayment
{
    public function __construct(
        private readonly RepDirectory $reps,
        private readonly RetailerDirectory $retailers,
        private readonly RepCommercialLimits $limits,
        private readonly InvoiceLifecycle $lifecycle,
        private readonly WalletLedger $wallet,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{receipt_no: string, retailer_id: int, invoice_no: string, amount: int, paid_at: string, client_op_id: string}  $data
     * @return array{payment: array{id: int}, wallet_balance: int, retailer_receivable: int}
     */
    public function __invoke(object $user, array $data): array
    {
        $repUserId = (int) $user->getAuthIdentifier();
        $channelId = $this->reps->channelIdForUser($repUserId);
        if ($channelId === null) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $retailerId = (int) $data['retailer_id'];
        if (! $this->retailers->exists($retailerId)) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        return Tenant::as($channelId, fn (): array => DB::transaction(function () use ($user, $repUserId, $channelId, $retailerId, $data): array {
            $opId = (string) $data['client_op_id'];
            $existing = Payment::query()
                ->where('rep_id', $repUserId)
                ->where('client_op_id', $opId)
                ->first();
            if ($existing !== null) {
                return $this->payload($existing, $repUserId, $retailerId);
            }

            $receiptNo = (string) $data['receipt_no'];
            if (Payment::query()->where('receipt_no', $receiptNo)->exists()) {
                throw new DomainException(__('finance.duplicate_receipt_no'), 'duplicate_receipt_no', 409);
            }

            $reservation = ReceiptReservation::query()->where('receipt_no', $receiptNo)->first();
            if ($reservation === null) {
                InvalidFields::throw(['receipt_no' => 'finance.receipt_not_reserved']);
            }
            if ((int) $reservation->rep_id !== $repUserId) {
                throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
            }
            if ($reservation->consumed_at !== null) {
                throw new DomainException(__('finance.duplicate_receipt_no'), 'duplicate_receipt_no', 409);
            }
            if ($reservation->expires_at !== null && $reservation->expires_at->lt(now())) {
                InvalidFields::throw(['receipt_no' => 'finance.receipt_expired']);
            }

            $invoice = Invoice::query()
                ->where('no', (string) $data['invoice_no'])
                ->where('retailer_id', $retailerId)
                ->first();
            if ($invoice === null || (int) $invoice->rep_id !== $repUserId) {
                throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
            }

            $amount = (int) $data['amount'];
            $cap = $this->limits->maxCashHold($channelId, $repUserId);
            if ($cap > 0 && ($this->wallet->balance($repUserId) + $amount) > $cap) {
                throw new DomainException(__('finance.cash_cap_exceeded'), 'cash_cap_exceeded', 403);
            }

            $payment = Payment::query()->create([
                'supply_channel_id' => $channelId,
                'retailer_id' => $retailerId,
                'rep_id' => $repUserId,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => 'cash',
                'source' => 'rep_app',
                'receipt_no' => $receiptNo,
                'paid_at' => $data['paid_at'],
                'client_op_id' => $opId,
            ]);

            $this->allocate($payment, $invoice, $retailerId, $amount);

            WalletTransaction::query()->create([
                'supply_channel_id' => $channelId,
                'rep_id' => $repUserId,
                'type' => 'collected',
                'amount' => $amount,
            ]);

            $reservation->forceFill([
                'consumed_at' => now(),
                'payment_id' => $payment->id,
            ])->save();

            $this->audit->record('finance.rep.collect', $user, 'payment', (int) $payment->id, [
                'amount' => $amount,
                'retailer_id' => $retailerId,
                'receipt_no' => $receiptNo,
            ], $channelId);

            return $this->payload($payment, $repUserId, $retailerId);
        }));
    }

    private function allocate(Payment $payment, Invoice $named, int $retailerId, int $amount): void
    {
        $remaining = $amount;
        $applied = $this->lifecycle->applyPayment($named, $remaining);
        if ($applied > 0) {
            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'invoice_id' => $named->id,
                'amount' => $applied,
            ]);
            $remaining -= $applied;
        }

        if ($remaining <= 0) {
            return;
        }

        $open = Invoice::query()
            ->where('retailer_id', $retailerId)
            ->where('status', 'open')
            ->where('id', '!=', $named->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        foreach ($open as $invoice) {
            if ($remaining <= 0) {
                break;
            }
            $applied = $this->lifecycle->applyPayment($invoice, $remaining);
            if ($applied <= 0) {
                continue;
            }
            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $applied,
            ]);
            $remaining -= $applied;
        }
    }

    /**
     * @return array{payment: array{id: int}, wallet_balance: int, retailer_receivable: int}
     */
    private function payload(Payment $payment, int $repUserId, int $retailerId): array
    {
        $receivable = (int) Invoice::query()
            ->where('retailer_id', $retailerId)
            ->where('status', 'open')
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->remaining());

        return [
            'payment' => ['id' => (int) $payment->id],
            'wallet_balance' => $this->wallet->balance($repUserId),
            'retailer_receivable' => $receivable,
        ];
    }
}
