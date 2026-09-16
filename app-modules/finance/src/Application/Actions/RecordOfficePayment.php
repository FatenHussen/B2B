<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ReceiptNumberReserver;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\InvoiceLifecycle;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\Payment;
use Modules\Finance\Domain\Models\PaymentAllocation;

final class RecordOfficePayment
{
    public function __construct(
        private readonly RetailerDirectory $retailers,
        private readonly ReceiptNumberReserver $receipts,
        private readonly InvoiceLifecycle $lifecycle,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{retailer_id: int, amount: int, method: string, invoice_id?: int|null}  $data
     * @return array{payment_id: int, retailer_receivable: int}
     */
    public function __invoke(object $actor, array $data): array
    {
        $this->assertSod($actor);

        $retailerId = (int) $data['retailer_id'];
        if (! $this->retailers->exists($retailerId)) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $channelId = (int) Tenant::currentId();
        $amount = (int) $data['amount'];
        $invoiceId = isset($data['invoice_id']) ? (int) $data['invoice_id'] : null;

        return DB::transaction(function () use ($actor, $retailerId, $channelId, $amount, $invoiceId, $data): array {
            $payment = Payment::query()->create([
                'supply_channel_id' => $channelId,
                'retailer_id' => $retailerId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'method' => (string) $data['method'],
                'source' => 'office',
                'receipt_no' => $this->receipts->reserve($channelId),
            ]);

            $remaining = $amount;
            if ($invoiceId !== null) {
                $named = Invoice::query()->whereKey($invoiceId)->where('retailer_id', $retailerId)->first();
                if ($named === null) {
                    throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
                }
                $applied = $this->lifecycle->applyPayment($named, $remaining);
                if ($applied > 0) {
                    PaymentAllocation::query()->create([
                        'payment_id' => $payment->id,
                        'invoice_id' => $named->id,
                        'amount' => $applied,
                    ]);
                    $remaining -= $applied;
                }
            }

            if ($remaining > 0) {
                $open = Invoice::query()
                    ->where('retailer_id', $retailerId)
                    ->where('status', 'open')
                    ->orderBy('created_at')
                    ->orderBy('id');
                if ($invoiceId !== null) {
                    $open->where('id', '!=', $invoiceId);
                }
                foreach ($open->get() as $invoice) {
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

            $receivable = (int) Invoice::query()
                ->where('retailer_id', $retailerId)
                ->where('status', 'open')
                ->get()
                ->sum(fn (Invoice $invoice) => $invoice->remaining());

            $this->audit->record('finance.payment', $actor, 'payment', (int) $payment->id, [
                'amount' => $amount,
                'retailer_id' => $retailerId,
            ], $channelId);

            return [
                'payment_id' => (int) $payment->id,
                'retailer_receivable' => $receivable,
            ];
        });
    }

    private function assertSod(object $actor): void
    {
        if (! method_exists($actor, 'can') || ! method_exists($actor, 'hasRole')) {
            return;
        }

        if ($actor->hasRole('channel_manager')) {
            return;
        }

        if ($actor->can('sc.orders.confirm') && $actor->can('sc.finance.payment')) {
            throw DomainException::of(ErrorCode::SodViolation, __('finance.sod_violation'));
        }
    }
}
