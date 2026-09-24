<?php

declare(strict_types=1);

namespace Modules\Promotion\Application\Actions;

use Modules\Promotion\Application\Services\OfferStatusRefresh;
use Modules\Promotion\Domain\Models\Offer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowOffer
{
    public function __construct(private readonly OfferStatusRefresh $refresh) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(int $id): array
    {
        $offer = Offer::query()
            ->with(['media', 'components', 'rewards', 'activityTypes', 'zones', 'groups', 'retailers'])
            ->find($id);
        if ($offer === null) {
            throw new NotFoundHttpException;
        }

        $this->refresh->refresh($offer);

        $reward = $offer->rewards->first();

        return [
            'id' => (int) $offer->id,
            'name' => $offer->name,
            'type' => $offer->type->value,
            'description' => $offer->description,
            'status' => $offer->status->value,
            'media' => $offer->media->map(fn ($m) => (string) $m->media_id)->values()->all(),
            'components' => $offer->components->map(fn ($c) => [
                'product_id' => (int) $c->product_id,
                'qty' => (int) $c->qty,
            ])->values()->all(),
            'rules' => $offer->rules ?? [],
            'rewards' => $reward === null ? [] : array_filter([
                'product_id' => $reward->product_id !== null ? (int) $reward->product_id : null,
                'qty' => (int) $reward->qty,
                'discount_percent' => $reward->discount_percent,
                'discount_amount' => $reward->discount_amount,
            ], fn ($v) => $v !== null),
            'targeting' => [
                'scope' => $offer->targeting_scope->value,
                'activity_type_ids' => $offer->activityTypes->pluck('activity_type_id')->map(fn ($v) => (int) $v)->values()->all(),
                'zone_ids' => $offer->zones->pluck('zone_id')->map(fn ($v) => (int) $v)->values()->all(),
                'group_ids' => $offer->groups->pluck('group_id')->map(fn ($v) => (int) $v)->values()->all(),
                'retailer_ids' => $offer->retailers->pluck('retailer_id')->map(fn ($v) => (int) $v)->values()->all(),
            ],
            'constraints' => [
                'starts_at' => $offer->starts_at?->timezone('Asia/Damascus')->toIso8601String(),
                'ends_at' => $offer->ends_at?->timezone('Asia/Damascus')->toIso8601String(),
                'total_qty' => $offer->total_qty,
                'per_retailer_max' => $offer->per_retailer_max,
                'per_order_max' => $offer->per_order_max,
                'min_invoice_value' => (int) $offer->min_invoice_value,
                'min_items' => $offer->min_items,
            ],
            'stackable' => (bool) $offer->stackable,
            'priority' => (int) $offer->priority,
        ];
    }
}
