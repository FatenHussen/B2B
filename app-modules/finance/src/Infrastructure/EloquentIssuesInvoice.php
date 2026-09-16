<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\InvoiceLine;

final class EloquentIssuesInvoice implements IssuesInvoice
{
    public function issue(int $subOrderId, int $channelId, int $retailerId, int $totalMinor, ?int $repId = null): array
    {
        return Tenant::as($channelId, function () use ($subOrderId, $channelId, $retailerId, $totalMinor, $repId): array {
            $existing = Invoice::query()->where('sub_order_id', $subOrderId)->first();
            if ($existing !== null) {
                return $this->map($existing);
            }

            $invoice = new Invoice;
            $invoice->fill([
                'supply_channel_id' => $channelId,
                'sub_order_id' => $subOrderId,
                'retailer_id' => $retailerId,
                'rep_id' => $repId,
                'no' => 'INV-tmp',
                'total' => $totalMinor,
                'paid_total' => 0,
                'credited_total' => 0,
            ]);
            $invoice->forceFill(['status' => 'open'])->save();
            $invoice->forceFill(['no' => 'INV-'.$invoice->id])->save();

            InvoiceLine::query()->create([
                'supply_channel_id' => $channelId,
                'invoice_id' => $invoice->id,
                'qty' => 1,
                'amount' => $totalMinor,
            ]);

            return $this->map($invoice);
        });
    }

    public function forSubOrder(int $subOrderId): ?array
    {
        $row = Tenant::withoutScope(fn () => Invoice::query()->where('sub_order_id', $subOrderId)->first());

        return $row !== null ? $this->map($row) : null;
    }

    public function replaceTotal(int $invoiceId, int $totalMinor): void
    {
        Tenant::withoutScope(function () use ($invoiceId, $totalMinor): void {
            $invoice = Invoice::query()->whereKey($invoiceId)->first();
            if ($invoice === null || $invoice->status !== 'open' || (int) $invoice->paid_total !== 0 || (int) $invoice->credited_total !== 0) {
                return;
            }
            $invoice->forceFill(['total' => $totalMinor])->save();
            InvoiceLine::query()->where('invoice_id', $invoiceId)->update(['amount' => $totalMinor]);
        });
    }

    /**
     * @return array{id: int, no: string, total: int}
     */
    private function map(Invoice $row): array
    {
        return [
            'id' => (int) $row->id,
            'no' => (string) $row->no,
            'total' => (int) $row->total,
        ];
    }
}
