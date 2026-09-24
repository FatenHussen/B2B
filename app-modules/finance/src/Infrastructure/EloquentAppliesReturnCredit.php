<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\AppliesReturnCredit;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\InvoiceLifecycle;
use Modules\Finance\Domain\Models\CreditNote;
use Modules\Finance\Domain\Models\CreditNoteLine;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\InvoiceLine;

/**
 * BR-13 financial half: approving a return credits the invoice for returned qty×unit.
 * Stock still goes through warehouse sort (EP-WH-030).
 */
final class EloquentAppliesReturnCredit implements AppliesReturnCredit
{
    public function __construct(
        private readonly SubOrderLifecycle $orders,
        private readonly InvoiceLifecycle $lifecycle,
        private readonly RecordsAudit $audit,
    ) {}

    public function apply(int $subOrderId, array $lines, string $reason, object $actor): void
    {
        if ($lines === []) {
            return;
        }

        $invoice = Invoice::query()->where('sub_order_id', $subOrderId)->orderByDesc('id')->first();
        if ($invoice === null || $invoice->status !== 'open') {
            return;
        }

        $orderLines = collect($this->orders->lines($subOrderId))->keyBy('id');
        $creditLines = [];
        $total = 0;
        foreach ($lines as $rl) {
            $ol = $orderLines->get((int) $rl['line_id']);
            if ($ol === null) {
                continue;
            }
            $productId = (int) ($ol['product_id'] ?? 0);
            $qty = (int) $rl['qty'];
            $amount = (int) ($ol['unit_price'] ?? 0) * $qty;
            $invoiceLine = InvoiceLine::query()
                ->where('invoice_id', $invoice->id)
                ->where('product_id', $productId)
                ->first();
            if ($invoiceLine === null || $amount <= 0) {
                continue;
            }
            $creditLines[] = [
                'invoice_line_id' => (int) $invoiceLine->id,
                'qty' => $qty,
                'amount' => $amount,
            ];
            $total += $amount;
        }

        if ($total <= 0 || $creditLines === []) {
            return;
        }

        DB::transaction(function () use ($invoice, $creditLines, $total, $reason, $actor, $subOrderId): void {
            $this->lifecycle->credit($invoice, $total);
            $note = CreditNote::query()->create([
                'supply_channel_id' => (int) Tenant::currentId(),
                'invoice_id' => $invoice->id,
                'no' => 'CN-tmp',
                'total' => $total,
                'reason' => $reason !== '' ? $reason : 'return_approved',
            ]);
            $note->forceFill(['no' => 'CN-'.$note->id])->save();
            foreach ($creditLines as $line) {
                CreditNoteLine::query()->create([
                    'credit_note_id' => $note->id,
                    'invoice_line_id' => $line['invoice_line_id'],
                    'qty' => $line['qty'],
                    'amount' => $line['amount'],
                ]);
            }
            $this->audit->record(
                'finance.credit_note_from_return',
                $actor,
                'credit_note',
                (int) $note->id,
                ['sub_order_id' => $subOrderId, 'total' => $total],
                (int) Tenant::currentId(),
            );
        });
    }
}
