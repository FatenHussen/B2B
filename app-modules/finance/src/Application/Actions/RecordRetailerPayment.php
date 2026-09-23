<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\InvoiceLifecycle;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\Payment;
use Modules\Finance\Domain\Models\PaymentAllocation;
use Modules\Finance\Domain\Models\ReceiptReservation;
use Modules\Finance\Domain\Models\WalletTransaction;

final class RecordRetailerPayment
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly InvoiceLifecycle $lifecycle,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{receipt_no: string, rep_id: int, supply_channel_id: int, invoice_no?: string|null, amount: int}  $data
     * @return array{payment: array{id: int}, retailer_receivable: int}
     */
    public function __invoke(object $user, array $data): array
    {
        if (! $this->shopping->isRetailer($user)) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $ctx = $this->shopping->for($user);
        $retailerId = (int) $ctx['retailer_id'];
        $channelId = (int) $data['supply_channel_id'];
        $repUserId = (int) $data['rep_id'];
        $amount = (int) $data['amount'];
        $receiptNo = (string) $data['receipt_no'];

        return Tenant::as($channelId, fn (): array => DB::transaction(function () use ($user, $retailerId, $channelId, $repUserId, $amount, $receiptNo, $data): array {
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

            $invoiceNo = isset($data['invoice_no']) ? trim((string) $data['invoice_no']) : '';
            $invoice = $invoiceNo !== ''
                ? Invoice::query()->where('no', $invoiceNo)->where('retailer_id', $retailerId)->first()
                : Invoice::query()
                    ->where('retailer_id', $retailerId)
                    ->where('status', 'open')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->first();

            if ($invoice === null) {
                throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
            }

            $payment = Payment::query()->create([
                'supply_channel_id' => $channelId,
                'retailer_id' => $retailerId,
                'rep_id' => $repUserId,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => 'cash',
                'source' => 'retailer_app',
                'receipt_no' => $receiptNo,
                'paid_at' => now(),
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

            $this->audit->record('finance.retailer.payment', $user, 'payment', (int) $payment->id, [
                'amount' => $amount,
                'retailer_id' => $retailerId,
                'receipt_no' => $receiptNo,
            ], $channelId);

            return [
                'payment' => ['id' => (int) $payment->id],
                'retailer_receivable' => $this->receivable($retailerId),
            ];
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

    private function receivable(int $retailerId): int
    {
        return (int) Invoice::query()
            ->where('retailer_id', $retailerId)
            ->where('status', 'open')
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->remaining());
    }
}
