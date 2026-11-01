<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\InvoiceLine;
use Modules\Finance\Domain\Models\Payment;
use Modules\Finance\Domain\Models\PaymentAllocation;
use Modules\Finance\Domain\Models\Settlement;
use Modules\Finance\Domain\Models\WalletTransaction;
use Modules\Fulfillment\Domain\Enums\HandoverStatus;
use Modules\Fulfillment\Domain\Models\Handover;
use Modules\Fulfillment\Domain\Models\HandoverItem;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Tenancy\Domain\Models\Warehouse;

/**
 * The rep field the demo history never wrote: a confirmed handover for every order
 * already on the road, and a wallet with open invoices, one collection and one
 * cash handover so the rep app can collect, withdraw, and open receivables.
 *
 * Accepted orders sit on the delivery list. They are moved to on_the_way with that
 * handover, because finishing them is otherwise rejected before the warehouse ever
 * opened a receipt. Safe to run twice.
 */
final class DemoRepFieldSeeder extends DemoSeeder
{
    public function run(): void
    {
        if (Tenant::currentId() === null) {
            foreach ($this->catalogChannelIds() as $channelId) {
                Tenant::as($channelId, fn () => $this->seedCurrentChannel());
            }

            return;
        }

        $this->seedCurrentChannel();
    }

    private function seedCurrentChannel(): void
    {
        $warehouseId = (int) Warehouse::query()->orderBy('id')->value('id');
        if ($warehouseId === 0) {
            return;
        }

        $this->seedHandovers($warehouseId);
        $this->seedWallets();
    }

    private function seedHandovers(int $warehouseId): void
    {
        $subs = SubOrder::query()
            ->whereNotNull('rep_id')
            ->whereIn('status', [
                SubOrderStatus::Accepted,
                SubOrderStatus::OnTheWay,
                SubOrderStatus::Delivered,
                SubOrderStatus::Undelivered,
            ])
            ->get();

        /** @var array<int, list<int>> $missing */
        $missing = [];
        foreach ($subs as $sub) {
            if ($sub->status === SubOrderStatus::Accepted) {
                $sub->status = SubOrderStatus::OnTheWay;
                $sub->save();
            }
            if (HandoverItem::query()->where('sub_order_id', $sub->id)->exists()) {
                continue;
            }
            $missing[(int) $sub->rep_id][] = (int) $sub->id;
        }

        foreach ($missing as $repId => $ids) {
            $handover = Handover::query()->create([
                'warehouse_id' => $warehouseId,
                'rep_id' => $repId,
                'status' => HandoverStatus::Confirmed,
                'temp_code' => '1001',
                'opened_at' => now()->subHour(),
                'confirmed_at' => now()->subHour(),
            ]);
            foreach ($ids as $id) {
                HandoverItem::query()->create([
                    'handover_id' => $handover->id,
                    'sub_order_id' => $id,
                ]);
            }
        }
    }

    private function seedWallets(): void
    {
        $repIds = SubOrder::query()
            ->where('status', SubOrderStatus::Delivered)
            ->whereNotNull('rep_id')
            ->distinct()
            ->pluck('rep_id');

        foreach ($repIds as $repId) {
            $repId = (int) $repId;
            if (WalletTransaction::query()->where('rep_id', $repId)->exists()) {
                continue;
            }

            $orders = [];
            $candidates = SubOrder::query()
                ->where('rep_id', $repId)
                ->where('status', SubOrderStatus::Delivered)
                ->where('total', '>', 0)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
            foreach ($candidates as $sub) {
                if (Invoice::query()->where('sub_order_id', $sub->id)->exists()) {
                    continue;
                }
                $orders[] = $sub;
                if (count($orders) === 3) {
                    break;
                }
            }
            if ($orders === []) {
                continue;
            }

            if (count($orders) === 1) {
                $this->collectPartial($orders[0], $repId);

                continue;
            }

            $partial = array_pop($orders);
            foreach ($orders as $sub) {
                $this->openInvoice($sub, 0);
            }
            $this->collectPartial($partial, $repId);
        }
    }

    private function openInvoice(SubOrder $sub, int $paid): Invoice
    {
        $channelId = $this->channelId();
        $invoice = new Invoice;
        $invoice->fill([
            'supply_channel_id' => $channelId,
            'sub_order_id' => $sub->id,
            'retailer_id' => (int) $sub->retailer_id,
            'rep_id' => (int) $sub->rep_id,
            'no' => 'INV-tmp',
            'total' => (int) $sub->total,
            'paid_total' => $paid,
            'credited_total' => 0,
        ]);
        $invoice->forceFill(['status' => 'open'])->save();
        $invoice->forceFill(['no' => 'INV-'.$invoice->id])->save();

        InvoiceLine::query()->create([
            'supply_channel_id' => $channelId,
            'invoice_id' => $invoice->id,
            'qty' => 1,
            'amount' => (int) $sub->total,
        ]);

        return $invoice;
    }

    private function collectPartial(SubOrder $sub, int $repId): void
    {
        $total = (int) $sub->total;
        $paid = $total > 200_000 ? 150_000 : max(1, intdiv($total, 2));
        if ($paid >= $total) {
            $paid = $total - 1;
        }
        if ($paid < 1) {
            $this->openInvoice($sub, 0);

            return;
        }

        $channelId = $this->channelId();
        $invoice = $this->openInvoice($sub, $paid);
        $payment = Payment::query()->create([
            'supply_channel_id' => $channelId,
            'retailer_id' => (int) $sub->retailer_id,
            'rep_id' => $repId,
            'invoice_id' => $invoice->id,
            'amount' => $paid,
            'method' => 'cash',
            'source' => 'rep_app',
            'receipt_no' => 'RCPT-D'.$sub->id,
            'paid_at' => now()->subHours(2),
            'client_op_id' => 'demo-collect-'.$sub->id,
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $paid,
        ]);
        WalletTransaction::query()->create([
            'supply_channel_id' => $channelId,
            'rep_id' => $repId,
            'type' => 'collected',
            'amount' => $paid,
        ]);

        $handed = $paid > 50_000 ? 50_000 : intdiv($paid, 3);
        if ($handed < 1 || $handed >= $paid) {
            return;
        }

        $settlement = Settlement::query()->create([
            'supply_channel_id' => $channelId,
            'rep_id' => $repId,
            'amount' => $handed,
            'operation_no' => 'OP-D'.$channelId.'-'.$repId,
            'operated_at' => now()->subHour(),
            'receipt_pdf_url' => '',
        ]);
        WalletTransaction::query()->create([
            'supply_channel_id' => $channelId,
            'rep_id' => $repId,
            'type' => 'settled',
            'amount' => $handed,
            'settlement_id' => $settlement->id,
            'operation_no' => $settlement->operation_no,
        ]);
    }
}
