<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RepLiveLocation;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;

final class TrackRetailerOrder
{
    /** @var array<string, string> */
    private const LABELS = [
        'pending' => 'تم استلام الطلب',
        'confirmed' => 'تم تأكيد الطلب',
        'accepted' => 'تم تأكيد الطلب',
        'assigned' => 'تم تأكيد الطلب',
        'processing' => 'قيد التجهيز',
        'awaiting_handover' => 'قيد التجهيز',
        'on_the_way' => 'المندوب في الطريق',
        'delivered' => 'تم استلام الطلب',
        'cancelled' => 'ملغى',
        'rejected' => 'مرفوض',
        'postponed' => 'مؤجل',
    ];

    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly RepDirectory $reps,
        private readonly RepLiveLocation $live,
        private readonly CatalogProductLookup $products,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $id): array
    {
        $ctx = $this->shopping->for($user);
        // acrossChannels(), per rule 10: `/app/retailer/*` sets no tenant, and a
        // retailer tracks their own order whichever channel fulfils it. `retailer_id`
        // below is the isolation that matters.
        $sub = SubOrder::query()
            ->acrossChannels()
            ->with(['events', 'lines'])
            ->where('retailer_id', $ctx['retailer_id'])
            ->find($id);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $rep = null;
        $mapAvailable = false;
        if ($sub->status === SubOrderStatus::OnTheWay && $sub->rep_id) {
            $ping = $this->live->latest((int) $sub->rep_id);
            $lat = $ping['lat'] ?? 33.5138;
            $lng = $ping['lng'] ?? 36.2765;
            $rep = [
                'name' => $this->reps->displayName((int) $sub->rep_id),
                'phone' => $this->reps->phone((int) $sub->rep_id),
                'lat' => $lat,
                'lng' => $lng,
            ];
            $mapAvailable = true;
        }

        $eta = null;
        if ($sub->scheduled_at !== null) {
            $from = $sub->scheduled_at->timezone('Asia/Damascus');
            $eta = [
                'from' => $from->toIso8601String(),
                'to' => $from->copy()->addHours(2)->toIso8601String(),
            ];
        }

        $lines = $sub->lines->map(function ($l) {
            $snap = $this->products->snapshot((int) $l->product_id, $l->variant_id ? (int) $l->variant_id : null);

            return [
                'id' => (int) $l->id,
                'name' => $snap['name'] ?? '',
                'image' => $snap['image'] ?? null,
                'qty' => (int) $l->qty,
                'price' => (int) $l->unit_price,
            ];
        })->all();

        return [
            'id' => (int) $sub->id,
            'sub_order_no' => $sub->sub_order_no,
            'status' => $sub->status->value,
            'created_at' => $sub->created_at?->timezone('Asia/Damascus')->toIso8601String(),
            'stages' => $sub->events->map(fn ($e) => [
                'key' => $e->stage,
                'label' => self::LABELS[$e->stage] ?? $e->stage,
                'at' => $e->at?->timezone('Asia/Damascus')->toIso8601String(),
            ])->all(),
            'rep' => $rep,
            'eta_minutes' => $sub->status === SubOrderStatus::OnTheWay ? 22 : null,
            'eta_window' => $eta,
            'lines' => $lines,
            'total' => (int) $sub->total,
            'can_rate_rep' => $sub->status === SubOrderStatus::Delivered && $sub->rep_id !== null,
            'map_available' => $mapAvailable,
        ];
    }
}
