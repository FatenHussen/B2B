<?php

declare(strict_types=1);

namespace Modules\Promotion\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\OfferConsumption;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Http\ApiController;
use Modules\Core\Support\MediaUrl;
use Modules\Promotion\Application\Actions\ActivateOffer;
use Modules\Promotion\Application\Actions\CreateOffer;
use Modules\Promotion\Application\Actions\ShowOffer;
use Modules\Promotion\Application\Actions\StopOffer;
use Modules\Promotion\Application\Actions\ToggleOfferFavorite;
use Modules\Promotion\Application\Actions\UpdateOffer;
use Modules\Promotion\Application\Queries\ShowOfferPerformance;
use Modules\Promotion\Application\Services\OfferStatusRefresh;
use Modules\Promotion\Domain\Models\Offer;
use Modules\Promotion\Domain\Models\RetailerOfferFavorite;
use Modules\Promotion\Infrastructure\EloquentOfferFeed;
use Modules\Promotion\Presentation\Http\Requests\ActivateOfferRequest;
use Modules\Promotion\Presentation\Http\Requests\StopOfferRequest;
use Modules\Promotion\Presentation\Http\Requests\StoreOfferRequest;
use Modules\Promotion\Presentation\Http\Requests\UpdateOfferRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OfferController extends ApiController
{
    public function index(Request $request, OfferStatusRefresh $refresh): JsonResponse
    {
        $page = QueryBuilder::for(Offer::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
            )
            ->defaultSort('-created_at')
            ->paginate(min((int) $request->get('per_page', 25), 100));

        return $this->paginated($page, function (Offer $offer) use ($refresh) {
            $refresh->refresh($offer);

            return [
                'id' => (int) $offer->id,
                'name' => $offer->name,
                'type' => $offer->type->value,
                'status' => $offer->status->value,
            ];
        });
    }

    public function store(StoreOfferRequest $request, CreateOffer $action): JsonResponse
    {
        return $this->created($action($request->user(), $request->validated()));
    }

    public function show(ShowOffer $action, int $id): JsonResponse
    {
        return $this->ok($action($id));
    }

    public function update(UpdateOfferRequest $request, UpdateOffer $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function stop(StopOfferRequest $request, StopOffer $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function activate(ActivateOfferRequest $request, ActivateOffer $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function performance(ShowOfferPerformance $query, int $id): JsonResponse
    {
        return $this->ok($query($id));
    }

    public function appIndex(
        Request $request,
        EloquentOfferFeed $feed,
        RetailerShoppingContext $shopping,
        RepSellingContext $selling,
        OfferConsumption $consumption,
    ): JsonResponse {
        $zoneId = (int) $request->input('filter.zone_id', 0);
        $activityId = (int) $request->input('filter.activity_type_id', 0);
        $channelIds = [];
        $retailerId = null;

        $user = $request->user();
        if ($shopping->isRetailer($user)) {
            $ctx = $shopping->for($user);
            $zoneId = $zoneId ?: $ctx['zone_id'];
            $activityId = $activityId ?: $ctx['activity_type_id'];
            $channelIds = $ctx['channel_ids'];
            $retailerId = (int) $ctx['retailer_id'];
        } elseif ($selling->isRep($user)) {
            $ctx = $selling->for($user);
            $zoneId = $zoneId ?: (int) ($ctx['default_zone_id'] ?? 0);
            $activityId = $activityId ?: (int) ($ctx['activity_type_id'] ?? 0);
            $channelIds = $ctx['channel_ids'];
        }

        $page = $feed->matching($zoneId, $activityId, $channelIds)
            ->paginate(min((int) $request->get('per_page', 25), 100));

        return $this->paginated($page, function (Offer $offer) use ($feed, $zoneId, $retailerId, $consumption) {
            if ($retailerId !== null) {
                $consumption->recordView((int) $offer->getKey(), $retailerId);
            }

            return $this->withFavorite($feed->card($offer, $zoneId), $retailerId);
        });
    }

    public function favorite(Request $request, ToggleOfferFavorite $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id));
    }

    public function appShow(
        Request $request,
        EloquentOfferFeed $feed,
        RetailerShoppingContext $shopping,
        RepSellingContext $selling,
        OfferConsumption $consumption,
        CatalogProductLookup $products,
        int $id,
    ): JsonResponse {
        $user = $request->user();
        $zoneId = 0;
        $activityId = 0;
        $channelIds = [];
        $retailerId = null;
        if ($shopping->isRetailer($user)) {
            $ctx = $shopping->for($user);
            $zoneId = $ctx['zone_id'];
            $activityId = $ctx['activity_type_id'];
            $channelIds = $ctx['channel_ids'];
            $retailerId = (int) $ctx['retailer_id'];
        } elseif ($selling->isRep($user)) {
            $ctx = $selling->for($user);
            $zoneId = (int) ($ctx['default_zone_id'] ?? 0);
            $activityId = (int) ($ctx['activity_type_id'] ?? 0);
            $channelIds = $ctx['channel_ids'];
        }

        $offer = $feed->matching($zoneId, $activityId, $channelIds)->whereKey($id)->first();
        if ($offer === null) {
            throw new NotFoundHttpException;
        }

        if ($retailerId !== null) {
            $consumption->recordView((int) $offer->getKey(), $retailerId);
        }

        $card = $feed->card($offer, $zoneId);
        $channelId = (int) $offer->supply_channel_id;

        $sameOffers = $feed->matching($zoneId, $activityId, [$channelId])
            ->whereKeyNot($offer->getKey())
            ->limit(10)
            ->get()
            ->map(fn (Offer $row) => $this->withFavorite($feed->card($row, $zoneId), $retailerId))
            ->all();

        $sameProducts = $products->sliderCards(
            $channelId,
            'channel',
            null,
            'recent',
            10,
            [
                'zone_id' => $zoneId,
                'activity_type_id' => $activityId,
                'channel_ids' => [$channelId],
            ],
        );

        $rules = is_array($offer->rules) ? $offer->rules : [];
        $terms = [];
        if (isset($rules['terms']) && is_array($rules['terms'])) {
            $terms = array_values(array_map('strval', $rules['terms']));
        } elseif (isset($rules['notes']) && is_string($rules['notes'])) {
            $terms = [$rules['notes']];
        }
        foreach ($rules as $key => $value) {
            if (in_array($key, ['terms', 'notes'], true)) {
                continue;
            }
            if (is_scalar($value)) {
                $terms[] = (string) $key.': '.(string) $value;
            }
        }
        if ($terms === []) {
            $percent = (int) ($card['discount_percent'] ?? 0);
            $terms = [
                $percent > 0 ? 'خصم '.$percent.'% عند إضافة الباقة للسلة' : 'السعر المعروض هو سعر الباقة',
                'الحد الأدنى للطلب: باقة واحدة',
                'يشمل الأصناف المذكورة أعلاه',
                'لا يمكن دمجه مع خصومات أخرى على الأصناف',
            ];
        }

        return $this->ok($this->withFavorite($card, $retailerId) + [
            'terms' => $terms,
            'images' => $offer->media->map(fn ($m) => MediaUrl::of((int) $m->media_id))->filter()->values()->all(),
            'long_description' => $offer->description,
            'icons' => [$offer->type->value],
            'same_company_offers' => $sameOffers,
            'same_company_products' => $sameProducts,
        ]);
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function withFavorite(array $card, ?int $retailerId): array
    {
        $card['is_favorite'] = $retailerId !== null && RetailerOfferFavorite::query()
            ->where('retailer_id', $retailerId)
            ->where('offer_id', (int) $card['id'])
            ->exists();

        return $card;
    }
}
