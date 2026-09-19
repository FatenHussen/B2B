<?php

declare(strict_types=1);

namespace Modules\Delivery\Application;

use Illuminate\Support\Carbon;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\HandoverGuard;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Contracts\ReceiptNumberReserver;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Events\DeliveryCompleted;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Delivery\Domain\Models\Delivery;
use Modules\Delivery\Domain\Models\DeliveryCompletion;
use Modules\Delivery\Domain\Models\DeliveryLine;
use Modules\Delivery\Domain\Models\RepLocationPing;
use Modules\Delivery\Domain\Models\RepRating;

final class DeliveryWorkspace
{
    public function __construct(
        private readonly SubOrderLifecycle $orders,
        private readonly CatalogProductLookup $products,
        private readonly IssuesInvoice $invoices,
        private readonly ReceiptNumberReserver $receipts,
        private readonly HandoverGuard $handover,
        private readonly RepDutyLookup $duty,
        private readonly RetailerShoppingContext $shopping,
    ) {}

    public function ensure(int $subOrderId, int $repId): Delivery
    {
        $existing = Delivery::query()->where('sub_order_id', $subOrderId)->first();
        if ($existing !== null) {
            return $existing;
        }
        $header = $this->orders->header($subOrderId);
        if ($header === null) {
            throw new DomainException(__('delivery.not_found'), 'not_found', 404);
        }
        $delivery = Delivery::query()->create([
            'sub_order_id' => $subOrderId,
            'rep_id' => $repId,
            'status' => 'accepted',
        ]);
        foreach ($this->orders->lines($subOrderId) as $line) {
            DeliveryLine::query()->create([
                'delivery_id' => $delivery->id,
                'sub_order_line_id' => $line['id'],
                'qty_expected' => $line['qty'],
                'qty_delivered' => 0,
                'action' => 'pending',
            ]);
        }

        return $delivery->fresh('lines');
    }

    /**
     * @return array<string, mixed>
     */
    public function listForRep(object $rep, ?int $zoneId): array
    {
        $ids = $this->orders->idsForRep((int) $rep->getAuthIdentifier(), ['on_the_way', 'accepted', 'delivered']);
        $cards = [];
        $headers = [];
        $today = now('Asia/Damascus')->toDateString();
        foreach ($ids as $sid) {
            $this->ensure($sid, (int) $rep->getAuthIdentifier());
            $header = $this->orders->header($sid);
            if ($header === null) {
                continue;
            }
            if ($zoneId && (int) $header['zone_id'] !== $zoneId) {
                continue;
            }
            if ($header['status'] === 'delivered') {
                $stamp = $header['updated_at'] ?? $header['created_at'];
                $day = is_string($stamp) ? substr($stamp, 0, 10) : null;
                if ($day !== $today) {
                    continue;
                }
            }
            $card = [
                'id' => $sid,
                'shop' => $header['shop_name'],
                'zone' => $header['zone_name'],
                'channel' => $header['channel_name'],
                'invoice_no' => $this->invoices->forSubOrder($sid)['no'] ?? null,
                'ordered_at' => $header['created_at'],
                'status' => $header['status'],
                'border_color' => $header['status'] === 'on_the_way' ? 'green' : ($header['status'] === 'delivered' ? 'gray' : 'blue'),
            ];
            $headers[$header['zone_name']][] = $card;
        }

        $zones = [];
        foreach ($headers as $name => $zoneCards) {
            $zones[] = [
                'name' => $name,
                'total' => count($zoneCards),
                'delivered' => count(array_filter($zoneCards, fn (array $card): bool => $card['status'] === 'delivered')),
                'cards' => $zoneCards,
            ];
        }

        return ['zones' => $zones];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(int $subOrderId, object $actor): array
    {
        $header = $this->assertOwned($subOrderId, $actor);
        $delivery = Delivery::query()->where('sub_order_id', $subOrderId)->first()
            ?? $this->ensure($subOrderId, (int) ($header['rep_id'] ?? 0));
        $orderLines = collect($this->orders->lines($subOrderId))->keyBy('id');
        $lines = $delivery->lines->map(function (DeliveryLine $line) use ($orderLines) {
            $src = $orderLines->get((int) $line->sub_order_line_id);

            return [
                'id' => (int) $line->id,
                'image' => null,
                'name' => $src['name'] ?? '',
                'brand' => $src['brand'] ?? null,
                'variant' => null,
                'qty' => (int) $line->qty_expected,
                'qty_delivered' => (int) $line->qty_delivered,
                'price' => $src['unit_price'] ?? 0,
                'status' => $line->action,
            ];
        })->all();

        $total = $this->invoiceTotal($delivery);

        return ['lines' => $lines, 'invoice_total' => $total];
    }

    /**
     * @return array{new_invoice_total: int}
     */
    public function patchLine(int $subOrderId, int $lineId, array $data, object $actor): array
    {
        $this->assertOwned($subOrderId, $actor);
        $delivery = Delivery::query()->where('sub_order_id', $subOrderId)->first();
        if ($delivery === null) {
            throw new DomainException(__('delivery.not_found'), 'not_found', 404);
        }
        $line = $delivery->lines()->whereKey($lineId)->first();
        if ($line === null) {
            throw new DomainException(__('delivery.not_found'), 'not_found', 404);
        }
        $qty = (int) ($data['qty_delivered'] ?? $data['qty_received'] ?? $line->qty_delivered);
        $line->qty_delivered = $qty;
        $line->action = $data['action'] ?? 'adjust';
        $line->reason = $data['reason'] ?? null;
        $line->save();
        $this->orders->replaceLineQty($subOrderId, (int) $line->sub_order_line_id, $qty);
        $invoice = $this->invoices->forSubOrder($subOrderId);
        $total = $this->invoiceTotal($delivery->fresh('lines'));
        if ($invoice !== null) {
            $this->invoices->replaceTotal($invoice['id'], $total);
        }

        return ['new_invoice_total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    public function complete(int $subOrderId, array $data, object $actor): array
    {
        $header = $this->assertOwned($subOrderId, $actor);
        if (! $this->handover->isConfirmedForSubOrder($subOrderId)) {
            throw new DomainException(__('delivery.no_handover'), 'illegal_transition', 409);
        }
        $delivery = $this->ensure($subOrderId, (int) ($header['rep_id'] ?? $actor->getAuthIdentifier()));
        if ($delivery->status === 'delivered') {
            $invoice = $this->invoices->forSubOrder($subOrderId);
            $completion = DeliveryCompletion::query()->where('delivery_id', $delivery->id)->first();

            return [
                'invoice' => ['no' => $invoice['no'] ?? null, 'total' => $invoice['total'] ?? $this->invoiceTotal($delivery)],
                'receipt_no' => $completion?->receipt_no,
                'ask_payment' => true,
            ];
        }
        foreach ($data['lines'] ?? [] as $row) {
            $line = $delivery->lines()->whereKey($row['line_id'])->first();
            if ($line === null) {
                continue;
            }
            $qty = (int) ($row['qty_delivered'] ?? $row['qty_received'] ?? $line->qty_expected);
            $line->qty_delivered = $qty;
            $line->action = $row['action'] ?? 'accept';
            $line->save();
            $this->orders->replaceLineQty($subOrderId, (int) $line->sub_order_line_id, $qty);
        }
        $total = $this->invoiceTotal($delivery->fresh('lines'));
        $invoice = $this->invoices->issue($subOrderId, (int) $header['channel_id'], (int) $header['retailer_id'], $total, (int) $header['rep_id']);
        $receipt = $this->receipts->reserveFor(
            (int) $header['channel_id'],
            (int) ($header['rep_id'] ?? $actor->getAuthIdentifier()),
        );
        DeliveryCompletion::query()->updateOrCreate(
            ['delivery_id' => $delivery->id],
            [
                'invoice_id' => $invoice['id'],
                'receipt_no' => $receipt,
                'delivered_at' => $data['delivered_at'] ?? now(),
                'signature' => $data['signature'] ?? null,
            ],
        );
        $delivery->status = 'delivered';
        $delivery->save();
        $this->orders->transition($subOrderId, 'delivered', $actor);
        event(new DeliveryCompleted($subOrderId, $invoice['id'], $invoice['no']));

        return [
            'invoice' => ['no' => $invoice['no'], 'total' => $total],
            'receipt_no' => $receipt,
            'ask_payment' => true,
        ];
    }

    /**
     * @return array{status: string}
     */
    public function postpone(int $subOrderId, array $data, object $actor): array
    {
        $this->assertOwned($subOrderId, $actor);
        $when = Carbon::parse((string) $data['scheduled_at']);
        $this->orders->postponeTo($subOrderId, $actor, $when->toIso8601String(), (string) $data['reason']);
        $delivery = Delivery::query()->where('sub_order_id', $subOrderId)->first();
        if ($delivery !== null) {
            $delivery->status = 'postponed';
            $delivery->reason = $data['reason'];
            $delivery->scheduled_at = $when;
            $delivery->save();
        }

        return ['status' => 'postponed'];
    }

    /**
     * @return array{status: string, border_color: string}
     */
    public function fail(int $subOrderId, array $data, object $actor): array
    {
        $this->assertOwned($subOrderId, $actor);
        $this->orders->markUndelivered($subOrderId, $actor, (string) $data['reason']);
        Delivery::query()->where('sub_order_id', $subOrderId)->update([
            'status' => 'undelivered',
            'reason' => $data['reason'],
        ]);

        return ['status' => 'undelivered', 'border_color' => 'red'];
    }

    /**
     * @param  list<array{lat: float, lng: float, at: string, accuracy?: int}>  $pings
     * @return array{accepted: int}
     */
    public function ping(object $rep, array $pings): array
    {
        if (! $this->duty->isOnDuty((int) $rep->getAuthIdentifier())) {
            throw new DomainException(__('delivery.off_duty'), 'validation_failed', 422);
        }
        $last = RepLocationPing::query()->where('rep_id', $rep->getAuthIdentifier())->orderByDesc('id')->first();
        if ($last !== null && $last->at && $last->at->gt(now()->subSeconds(30))) {
            throw new DomainException(__('delivery.rate_limited'), 'rate_limited', 429);
        }
        $n = 0;
        foreach ($pings as $p) {
            RepLocationPing::query()->create([
                'rep_id' => $rep->getAuthIdentifier(),
                'lat' => $p['lat'],
                'lng' => $p['lng'],
                'at' => $p['at'],
                'accuracy' => $p['accuracy'] ?? null,
            ]);
            $n++;
        }

        return ['accepted' => $n];
    }

    /**
     * @return array{success: bool}
     */
    public function rate(object $retailer, int $repId, array $data): array
    {
        $ctx = $this->shopping->for($retailer);
        $delivery = Delivery::query()
            ->where('rep_id', $repId)
            ->where('status', 'delivered')
            ->orderByDesc('id')
            ->first();
        if ($delivery === null) {
            throw new DomainException(__('delivery.not_found'), 'not_found', 404);
        }
        RepRating::query()->updateOrCreate(
            [
                'retailer_id' => $ctx['retailer_id'],
                'rep_id' => $repId,
                'sub_order_id' => $delivery->sub_order_id,
            ],
            [
                'stars' => $data['stars'],
                'note' => $data['note'] ?? null,
                'tags' => $data['tags'] ?? [],
            ],
        );

        return ['success' => true];
    }

    private function invoiceTotal(Delivery $delivery): int
    {
        $total = 0;
        $orderLines = collect($this->orders->lines((int) $delivery->sub_order_id))->keyBy('id');
        foreach ($delivery->lines as $line) {
            $src = $orderLines->get((int) $line->sub_order_line_id);
            $qty = $line->action === 'pending' ? (int) $line->qty_expected : (int) $line->qty_delivered;
            $price = (int) ($src['unit_price'] ?? 0);
            $total += $price * $qty;
        }

        return $total;
    }

    /**
     * The sub-order header, if and only if this actor is one of its two parties: the
     * rep it is assigned to, or the retailer it was placed by. Anyone else — another
     * rep, another retailer — gets 404, never 403: existence is not disclosed. Every
     * method that reads or moves a delivery starts here.
     *
     * @return array<string, mixed>
     */
    private function assertOwned(int $subOrderId, object $actor): array
    {
        $header = $this->orders->header($subOrderId);
        if ($header === null) {
            throw new DomainException(__('delivery.not_found'), 'not_found', 404);
        }

        $owns = $this->shopping->isRetailer($actor)
            ? (int) $header['retailer_id'] === $this->shopping->for($actor)['retailer_id']
            : (int) ($header['rep_id'] ?? 0) === (int) $actor->getAuthIdentifier();

        if (! $owns) {
            throw new DomainException(__('delivery.not_found'), 'not_found', 404);
        }

        return $header;
    }
}
