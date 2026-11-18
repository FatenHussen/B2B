<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;

final class ShowRetailerOrder
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly CatalogProductLookup $products,
        private readonly IssuesInvoice $invoices,
        private readonly RepDirectory $reps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $id): array
    {
        $ctx = $this->shopping->for($user);
        // `acrossChannels()` with the reason, per rule 10. A retailer buys from several
        // channels and reads their own orders across all of them; the `/app/retailer/*`
        // group sets no tenant, so the channel scope would throw rather than filter. The
        // isolation that matters here is `retailer_id` — a retailer sees their own orders
        // and no one else's, which is the line below and is not a channel question.
        $sub = SubOrder::query()
            ->acrossChannels()
            ->with(['lines', 'events'])
            ->where('retailer_id', $ctx['retailer_id'])
            ->find($id);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $rep = null;
        if ($sub->status === SubOrderStatus::OnTheWay && $sub->rep_id) {
            $rep = [
                'name' => $this->reps->displayName((int) $sub->rep_id),
                'phone' => $this->reps->phone((int) $sub->rep_id),
            ];
        }

        $lines = $sub->lines->map(function ($l) {
            $snap = $this->products->snapshot((int) $l->product_id, $l->variant_id ? (int) $l->variant_id : null);
            $price = (int) $l->unit_price;
            $discount = (int) $l->discount;
            $before = $price + $discount;
            $percent = $before > 0 ? (int) round(($discount * 100) / $before) : 0;
            $attrs = [];
            if (! empty($snap['variant'])) {
                $attrs['المتغير'] = (string) $snap['variant'];
            }

            return [
                'id' => (int) $l->id,
                'name' => $snap['name'] ?? '',
                'image' => $snap['image'] ?? null,
                'qty' => (int) $l->qty,
                'attrs' => $attrs,
                'price_before' => $before,
                'discount' => $discount,
                'discount_percent' => $percent,
                'price' => $price,
                'line_total' => (int) $l->line_total,
            ];
        })->all();

        $eta = null;
        if ($sub->scheduled_at !== null) {
            $from = $sub->scheduled_at->timezone('Asia/Damascus');
            $eta = [
                'from' => $from->toIso8601String(),
                'to' => $from->copy()->addHours(2)->toIso8601String(),
            ];
        }

        return [
            'id' => (int) $sub->id,
            'sub_order_no' => $sub->sub_order_no,
            'status' => $sub->status->value,
            'created_at' => $sub->created_at?->timezone('Asia/Damascus')->toIso8601String(),
            'total' => (int) $sub->total,
            'products_total' => (int) $sub->subtotal,
            'discount_total' => (int) $sub->discount,
            'eta_window' => $eta,
            'lines' => $lines,
            'invoice' => $this->invoices->forSubOrder((int) $sub->id),
            'timeline' => $sub->events->map(fn ($e) => [
                'stage' => $e->stage,
                'at' => $e->at?->timezone('Asia/Damascus')->toIso8601String(),
            ])->all(),
            'rep' => $rep,
            'can_track' => in_array($sub->status, [
                SubOrderStatus::Processing,
                SubOrderStatus::AwaitingHandover,
                SubOrderStatus::OnTheWay,
            ], true),
            'can_reorder' => true,
            'can_cancel' => $sub->status === SubOrderStatus::Pending,
        ];
    }
}
