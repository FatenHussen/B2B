<?php

declare(strict_types=1);

namespace Modules\Promotion\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Http\ApiController;
use Modules\Core\Support\MediaUrl;
use Modules\Promotion\Application\Actions\CreateOffer;
use Modules\Promotion\Application\Actions\StopOffer;
use Modules\Promotion\Domain\Models\Offer;
use Modules\Promotion\Infrastructure\EloquentOfferFeed;
use Modules\Promotion\Presentation\Http\Requests\StopOfferRequest;
use Modules\Promotion\Presentation\Http\Requests\StoreOfferRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OfferController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $page = QueryBuilder::for(Offer::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
            )
            ->defaultSort('-created_at')
            ->paginate(min((int) $request->get('per_page', 25), 100));

        return $this->paginated($page, fn (Offer $offer) => [
            'id' => (int) $offer->id,
            'name' => $offer->name,
            'type' => $offer->type->value,
            'status' => $offer->status->value,
        ]);
    }

    public function store(StoreOfferRequest $request, CreateOffer $action): JsonResponse
    {
        return $this->created($action($request->user(), $request->validated()));
    }

    public function stop(StopOfferRequest $request, StopOffer $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function performance(int $id): JsonResponse
    {
        Offer::query()->findOrFail($id);

        return $this->ok([
            'applied_count' => 0,
            'linked_sales' => 0,
            'discount_given' => 0,
            'net_margin' => 0,
            'retailers_count' => 0,
            'by_zone' => [],
            'conversion_rate' => 0,
        ]);
    }

    public function appIndex(Request $request, EloquentOfferFeed $feed, RetailerShoppingContext $shopping): JsonResponse
    {
        $zoneId = (int) $request->input('filter.zone_id', 0);
        $activityId = (int) $request->input('filter.activity_type_id', 0);
        $channelIds = [];

        $user = $request->user();
        if ($shopping->isRetailer($user)) {
            $ctx = $shopping->for($user);
            $zoneId = $zoneId ?: $ctx['zone_id'];
            $activityId = $activityId ?: $ctx['activity_type_id'];
            $channelIds = $ctx['channel_ids'];
        }

        $page = $feed->matching($zoneId, $activityId, $channelIds)
            ->paginate(min((int) $request->get('per_page', 25), 100));

        return $this->paginated($page, fn (Offer $offer) => $feed->card($offer, $zoneId));
    }

    public function appShow(Request $request, EloquentOfferFeed $feed, RetailerShoppingContext $shopping, int $id): JsonResponse
    {
        $user = $request->user();
        $zoneId = 0;
        $activityId = 0;
        $channelIds = [];
        if ($shopping->isRetailer($user)) {
            $ctx = $shopping->for($user);
            $zoneId = $ctx['zone_id'];
            $activityId = $ctx['activity_type_id'];
            $channelIds = $ctx['channel_ids'];
        }

        $offer = $feed->matching($zoneId, $activityId, $channelIds)->whereKey($id)->first();
        if ($offer === null) {
            throw new NotFoundHttpException;
        }

        $card = $feed->card($offer, $zoneId);

        return $this->ok($card + [
            'images' => $offer->media->map(fn ($m) => MediaUrl::of((int) $m->media_id))->filter()->values()->all(),
            'long_description' => $offer->description,
            'icons' => [$offer->type->value],
            'same_company_offers' => [],
            'same_company_products' => [],
        ]);
    }
}
