<?php

declare(strict_types=1);

namespace Modules\Promotion\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RetailerGroupDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Promotion\Application\Services\OfferStatusRefresh;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Enums\OfferType;
use Modules\Promotion\Domain\Enums\TargetingScope;
use Modules\Promotion\Domain\Models\Offer;
use Modules\Promotion\Domain\Models\OfferActivityType;
use Modules\Promotion\Domain\Models\OfferComponent;
use Modules\Promotion\Domain\Models\OfferGroup;
use Modules\Promotion\Domain\Models\OfferMedia;
use Modules\Promotion\Domain\Models\OfferRetailer;
use Modules\Promotion\Domain\Models\OfferReward;
use Modules\Promotion\Domain\Models\OfferZone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateOffer
{
    public function __construct(
        private readonly ReferenceDirectory $refs,
        private readonly RetailerGroupDirectory $groups,
        private readonly RecordsAudit $audit,
        private readonly OfferStatusRefresh $refresh,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int}
     */
    public function __invoke(object $actor, int $id, array $data): array
    {
        $offer = Offer::query()->find($id);
        if ($offer === null) {
            throw new NotFoundHttpException;
        }

        $this->refresh->refresh($offer);
        if (in_array($offer->status, [OfferStatus::Stopped, OfferStatus::Expired], true)) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('promotion.illegal_transition'));
        }

        $targeting = $data['targeting'] ?? [];
        $constraints = $data['constraints'] ?? [];
        $zoneIds = array_map('intval', $targeting['zone_ids'] ?? []);
        if (! $this->refs->allZonesExist($zoneIds)) {
            InvalidFields::throw(['targeting.zone_ids' => 'promotion.zone_not_found']);
        }
        $activityIds = array_map('intval', $targeting['activity_type_ids'] ?? []);
        if (! $this->refs->allActivityTypesExist($activityIds)) {
            InvalidFields::throw(['targeting.activity_type_ids' => 'promotion.activity_type_not_found']);
        }
        $groupIds = array_map('intval', $targeting['group_ids'] ?? []);
        if (! $this->groups->allExistInChannel((int) Tenant::currentId(), $groupIds)) {
            InvalidFields::throw(['targeting.group_ids' => 'promotion.group_not_found']);
        }

        DB::transaction(function () use ($offer, $data, $targeting, $constraints, $zoneIds, $activityIds, $groupIds, $actor): void {
            $offer->fill([
                'name' => $data['name'],
                'type' => OfferType::from((string) $data['type']),
                'description' => $data['description'] ?? null,
                'stackable' => (bool) ($data['stackable'] ?? false),
                'priority' => (int) ($data['priority'] ?? 0),
                'starts_at' => $constraints['starts_at'] ?? null,
                'ends_at' => $constraints['ends_at'] ?? null,
                'total_qty' => $constraints['total_qty'] ?? null,
                'per_retailer_max' => $constraints['per_retailer_max'] ?? null,
                'per_order_max' => $constraints['per_order_max'] ?? null,
                'min_invoice_value' => (int) ($constraints['min_invoice_value'] ?? 0),
                'min_items' => $constraints['min_items'] ?? null,
                'targeting_scope' => TargetingScope::tryFrom((string) ($targeting['scope'] ?? 'all')) ?? TargetingScope::All,
                'rules' => $data['rules'] ?? [],
            ])->save();

            OfferMedia::query()->where('offer_id', $offer->id)->delete();
            foreach ($data['media'] ?? [] as $i => $mediaId) {
                OfferMedia::query()->create(['offer_id' => $offer->id, 'media_id' => (int) $mediaId, 'order' => $i]);
            }

            OfferComponent::query()->where('offer_id', $offer->id)->delete();
            foreach ($data['components'] ?? [] as $component) {
                OfferComponent::query()->create([
                    'offer_id' => $offer->id,
                    'product_id' => (int) $component['product_id'],
                    'qty' => (int) ($component['qty'] ?? 1),
                ]);
            }

            OfferReward::query()->where('offer_id', $offer->id)->delete();
            $rewards = $data['rewards'] ?? null;
            if (is_array($rewards) && $rewards !== []
                && (isset($rewards['product_id']) || isset($rewards['discount_amount']) || isset($rewards['discount_percent']))) {
                OfferReward::query()->create([
                    'offer_id' => $offer->id,
                    'product_id' => $rewards['product_id'] ?? null,
                    'qty' => (int) ($rewards['qty'] ?? 1),
                    'discount_percent' => $rewards['discount_percent'] ?? null,
                    'discount_amount' => $rewards['discount_amount'] ?? null,
                ]);
            }

            OfferActivityType::query()->where('offer_id', $offer->id)->delete();
            foreach ($activityIds as $aid) {
                OfferActivityType::query()->create(['offer_id' => $offer->id, 'activity_type_id' => $aid]);
            }
            OfferZone::query()->where('offer_id', $offer->id)->delete();
            foreach ($zoneIds as $zid) {
                OfferZone::query()->create(['offer_id' => $offer->id, 'zone_id' => $zid]);
            }
            OfferGroup::query()->where('offer_id', $offer->id)->delete();
            foreach ($groupIds as $gid) {
                OfferGroup::query()->create(['offer_id' => $offer->id, 'group_id' => (int) $gid]);
            }
            OfferRetailer::query()->where('offer_id', $offer->id)->delete();
            foreach ($targeting['retailer_ids'] ?? [] as $rid) {
                OfferRetailer::query()->create(['offer_id' => $offer->id, 'retailer_id' => (int) $rid]);
            }

            $this->audit->record('promotion.offer.update', $actor, 'offer', (int) $offer->id, [
                'after' => ['name' => $offer->name, 'type' => $offer->type->value],
            ], Tenant::currentId());
        });

        return ['id' => (int) $offer->id];
    }
}
