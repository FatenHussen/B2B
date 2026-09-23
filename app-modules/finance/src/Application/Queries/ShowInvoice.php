<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\InvoiceLine;

final class ShowInvoice
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(int $id): array
    {
        $invoice = Invoice::query()->whereKey($id)->first();
        if ($invoice === null) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $lines = InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->get()
            ->map(fn (InvoiceLine $line) => [
                'id' => (int) $line->id,
                'product_id' => $line->product_id !== null ? (int) $line->product_id : null,
                'qty' => (int) $line->qty,
                'amount' => (int) $line->amount,
            ])
            ->all();

        return [
            'id' => (int) $invoice->id,
            'no' => (string) $invoice->no,
            'status' => (string) $invoice->status,
            'retailer_id' => (int) $invoice->retailer_id,
            'rep_id' => $invoice->rep_id !== null ? (int) $invoice->rep_id : null,
            'total' => (int) $invoice->total,
            'paid_total' => (int) $invoice->paid_total,
            'credited_total' => (int) $invoice->credited_total,
            'remaining' => $invoice->remaining(),
            'lines' => $lines,
            'created_at' => $invoice->created_at?->timezone('Asia/Damascus')->toIso8601String(),
        ];
    }
}
