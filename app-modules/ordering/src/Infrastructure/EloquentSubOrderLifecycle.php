<?php

declare(strict_types=1);

namespace Modules\Ordering\Infrastructure;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\SubOrderStateMachine;

final class EloquentSubOrderLifecycle implements SubOrderLifecycle
{
    public function __construct(
        private readonly SubOrderStateMachine $machine,
        private readonly CatalogProductLookup $products,
        private readonly ChannelDirectory $channels,
        private readonly ReferenceDirectory $refs,
        private readonly RepDirectory $reps,
        private readonly IssuesInvoice $invoices,
        private readonly RetailerDirectory $retailers,
    ) {}

    public function header(int $subOrderId): ?array
    {
        $sub = Tenant::withoutScope(fn () => SubOrder::query()->find($subOrderId));
        if ($sub === null) {
            return null;
        }
        $shop = $this->retailers->find((int) $sub->retailer_id);
        $phone = $shop !== null ? $this->retailers->phone((int) $sub->retailer_id) : null;

        return [
            'id' => (int) $sub->id,
            'sub_order_no' => $sub->sub_order_no,
            'status' => $sub->status->value,
            'channel_id' => (int) $sub->channel_id,
            'retailer_id' => (int) $sub->retailer_id,
            'zone_id' => $sub->zone_id ? (int) $sub->zone_id : null,
            'rep_id' => $sub->rep_id ? (int) $sub->rep_id : null,
            'rep_user_id' => $sub->rep_id ? (int) $sub->rep_id : null,
            'total' => (int) $sub->total,
            'scheduled_at' => $sub->scheduled_at?->timezone('Asia/Damascus')->toIso8601String(),
            'shop_name' => $shop['shop_name'] ?? '',
            'zone_name' => $sub->zone_id ? ($this->refs->zoneName((int) $sub->zone_id) ?? '') : '',
            'channel_name' => $this->channels->name((int) $sub->channel_id) ?? '',
            'address' => $shop['address'] ?? null,
            'phone' => is_string($phone) ? $phone : null,
        ];
    }

    public function lines(int $subOrderId): array
    {
        $sub = Tenant::withoutScope(fn () => SubOrder::query()->with('lines')->find($subOrderId));
        if ($sub === null) {
            return [];
        }
        $out = [];
        foreach ($sub->lines as $line) {
            $snap = $this->products->snapshot((int) $line->product_id, $line->variant_id ? (int) $line->variant_id : null);
            $out[] = [
                'id' => (int) $line->id,
                'product_id' => (int) $line->product_id,
                'variant_id' => $line->variant_id ? (int) $line->variant_id : null,
                'qty' => (int) $line->qty,
                'unit_price' => (int) $line->unit_price,
                'discount' => (int) $line->discount,
                'line_total' => (int) $line->line_total,
                'name' => $snap['name'] ?? '',
                'brand' => $snap['brand'] ?? null,
            ];
        }

        return $out;
    }

    public function transition(int $subOrderId, string $to, object $actor, ?string $stage = null): void
    {
        $sub = Tenant::withoutScope(fn () => SubOrder::query()->find($subOrderId));
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }
        $this->machine->assert($sub->status, $to);
        $target = $this->machine->target($to);
        Tenant::withoutScope(function () use ($sub, $target, $actor, $stage): void {
            $sub->status = $target;
            $sub->save();
            SubOrderEvent::query()->create([
                'sub_order_id' => $sub->id,
                'stage' => $stage ?? $target->value,
                'at' => now(),
                'actor_type' => $actor::class,
                'actor_id' => method_exists($actor, 'getAuthIdentifier') ? $actor->getAuthIdentifier() : null,
            ]);
        });
    }

    public function status(int $subOrderId): ?string
    {
        $status = Tenant::withoutScope(fn () => SubOrder::query()->whereKey($subOrderId)->value('status'));

        return is_string($status) ? $status : null;
    }

    public function replaceLineQty(int $subOrderId, int $lineId, int $qty): int
    {
        $sub = Tenant::withoutScope(fn () => SubOrder::query()->with('lines')->find($subOrderId));
        if ($sub === null) {
            return 0;
        }
        $line = $sub->lines->firstWhere('id', $lineId);
        if ($line === null) {
            return (int) $sub->total;
        }
        $line->qty = $qty;
        $line->line_total = (int) $line->unit_price * $qty - (int) $line->discount;
        $line->save();
        $total = (int) $sub->lines()->sum('line_total');
        $sub->forceFill(['total' => $total])->save();
        $invoice = $this->invoices->forSubOrder($subOrderId);
        if ($invoice !== null) {
            $this->invoices->replaceTotal($invoice['id'], $total);
        }

        return $total;
    }

    public function idsForRep(int $repUserId, array $statuses): array
    {
        return Tenant::withoutScope(fn () => SubOrder::query()
            ->where('rep_id', $repUserId)
            ->whereIn('status', $statuses)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    public function idsForRetailer(int $retailerId): array
    {
        // Cross-channel by design, like the rest of this contract: the caller names the
        // owner, and a retailer's orders span every channel that serves them.
        return Tenant::withoutScope(fn () => SubOrder::query()
            ->where('retailer_id', $retailerId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }
}
