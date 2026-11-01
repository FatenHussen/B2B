<?php

declare(strict_types=1);

namespace Modules\Delivery\Application;

use Illuminate\Support\Carbon;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\HandoverGuard;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Contracts\ReceiptNumberReserver;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Core\Contracts\RetailerDirectory;
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
        private readonly IssuesInvoice $invoices,
        private readonly ReceiptNumberReserver $receipts,
        private readonly HandoverGuard $handover,
        private readonly RepDutyLookup $duty,
        private readonly RetailerShoppingContext $shopping,
        private readonly CatalogProductLookup $products,
        private readonly RetailerDirectory $shops,
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
        /** @var array<string, array{id: int|null, name: string, cards: list<array<string, mixed>>}> $groups */
        $groups = [];
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
            $name = $header['zone_name'];
            if (! isset($groups[$name])) {
                $groups[$name] = [
                    'id' => $header['zone_id'],
                    'name' => $name,
                    'cards' => [],
                ];
            }
            $groups[$name]['cards'][] = [
                'id' => $sid,
                'sub_order_no' => $header['sub_order_no'],
                'shop' => $header['shop_name'],
                'zone' => $header['zone_name'],
                'channel' => $header['channel_name'],
                'invoice_no' => $this->invoices->forSubOrder($sid)['no'] ?? null,
                'ordered_at' => $header['created_at'],
                'status' => $header['status'],
                'total' => (int) $header['total'],
                'border_color' => $header['status'] === 'on_the_way' ? 'green' : ($header['status'] === 'delivered' ? 'gray' : 'blue'),
            ];
        }

        $zones = [];
        foreach ($groups as $group) {
            $zoneCards = $group['cards'];
            $zones[] = [
                'id' => $group['id'],
                'name' => $group['name'],
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
            $src = is_array($src) ? $src : [];

            return $this->presentLine($line, $src);
        })->all();

        $total = $this->invoiceTotal($delivery);
        $invoice = $this->invoices->forSubOrder($subOrderId);
        $pin = $this->shops->location((int) $header['retailer_id']);

        return [
            'shop' => $header['shop_name'],
            'zone' => $header['zone_name'],
            'channel' => $header['channel_name'],
            'address' => $header['address'],
            'phone' => $header['phone'],
            'lat' => $pin['lat'] ?? null,
            'lng' => $pin['lng'] ?? null,
            'sub_order_no' => $header['sub_order_no'],
            'invoice_no' => $invoice['no'] ?? null,
            'created_at' => $header['created_at'],
            'scheduled_at' => $header['scheduled_at'],
            'lines' => $lines,
            'invoice_total' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $src
     * @return array<string, mixed>
     */
    private function presentLine(DeliveryLine $line, array $src): array
    {
        $productId = (int) ($src['product_id'] ?? 0);
        $variantId = isset($src['variant_id']) ? (int) $src['variant_id'] : null;
        $snap = $productId > 0 ? $this->products->snapshot($productId, $variantId) : null;
        $card = is_array($snap) ? $snap : [];
        $qty = (int) $line->qty_expected;
        $unit = (int) ($src['unit_price'] ?? 0);
        $name = is_string($src['name'] ?? null) && $src['name'] !== '' ? $src['name'] : (string) ($card['name'] ?? '');

        return [
            'id' => (int) $line->id,
            'image' => $card['image'] ?? null,
            'name' => $name,
            'brand' => $src['brand'] ?? ($card['brand'] ?? null),
            'variant' => $card['variant'] ?? null,
            'sale_unit' => $card['sale_unit'] ?? null,
            'unit_factors' => $card['unit_factors'] ?? [],
            'qty' => $qty,
            'qty_delivered' => (int) $line->qty_delivered,
            'price' => $unit,
            'line_total' => (int) ($src['line_total'] ?? ($unit * $qty)),
            'status' => $line->action,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function complete(int $subOrderId, array $data, object $actor): array
    {
        $header = $this->assertOwned($subOrderId, $actor);
        // `on_the_way` is the state the warehouse release produces. Seeded history
        // and any release that did not keep the handover row are already past that
        // gate, so finishing them must not 409. An earlier status still requires
        // a confirmed handover — that is the rep's warehouse receipt.
        $released = in_array($header['status'] ?? '', ['on_the_way', 'delivered'], true);
        if (! $released && ! $this->handover->isConfirmedForSubOrder($subOrderId)) {
            throw new DomainException(__('delivery.no_handover'), 'illegal_transition', 409);
        }
        $delivery = $this->ensure($subOrderId, (int) ($header['rep_id'] ?? $actor->getAuthIdentifier()));
        if ($delivery->status === 'delivered') {
            $invoice = $this->invoices->forSubOrder($subOrderId);
            $completion = DeliveryCompletion::query()->where('delivery_id', $delivery->id)->first();

            return $this->receipt(
                $header,
                $invoice,
                $completion?->receipt_no,
                $invoice['total'] ?? $this->invoiceTotal($delivery),
                $completion?->delivered_at?->timezone('Asia/Damascus')->toIso8601String(),
            );
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

        $stamp = isset($data['delivered_at']) ? Carbon::parse((string) $data['delivered_at']) : now();

        return $this->receipt($header, $invoice, $receipt, $total, $stamp->timezone('Asia/Damascus')->toIso8601String());
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array{id: int, no: string, total: int}|null  $invoice
     * @return array<string, mixed>
     */
    private function receipt(array $header, ?array $invoice, ?string $receiptNo, int $total, ?string $deliveredAt): array
    {
        return [
            'shop' => $header['shop_name'],
            'delivered_at' => $deliveredAt,
            'invoice' => ['no' => $invoice['no'] ?? null, 'total' => $invoice['total'] ?? $total],
            'receipt_no' => $receiptNo,
            'ask_payment' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, scheduled_at: string}
     */
    public function postpone(int $subOrderId, array $data, object $actor): array
    {
        $this->assertOwned($subOrderId, $actor);
        $when = Carbon::parse((string) $data['scheduled_at']);
        $reason = $this->storedReason($data);
        $this->orders->postponeTo($subOrderId, $actor, $when->toIso8601String(), $reason);
        $delivery = Delivery::query()->where('sub_order_id', $subOrderId)->first();
        if ($delivery !== null) {
            $delivery->status = 'postponed';
            $delivery->reason = $reason;
            $delivery->scheduled_at = $when;
            $delivery->save();
        }

        return [
            'status' => 'postponed',
            'scheduled_at' => $when->timezone('Asia/Damascus')->toIso8601String(),
        ];
    }

    /**
     * Postpone and fail share one reason column. The optional note is appended. The column is 255.
     *
     * @param  array<string, mixed>  $data
     */
    private function storedReason(array $data): string
    {
        $reason = trim((string) $data['reason']);
        $note = trim((string) ($data['note'] ?? ''));
        if ($note !== '') {
            $reason = $reason.' — '.$note;
        }

        return mb_substr($reason, 0, 255);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, border_color: string}
     */
    public function fail(int $subOrderId, array $data, object $actor): array
    {
        $this->assertOwned($subOrderId, $actor);
        $reason = $this->storedReason($data);
        $this->orders->markUndelivered($subOrderId, $actor, $reason);
        Delivery::query()->where('sub_order_id', $subOrderId)->update([
            'status' => 'undelivered',
            'reason' => $reason,
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
     * @param  array<string, mixed>  $data
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
