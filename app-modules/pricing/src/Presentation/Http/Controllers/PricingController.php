<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Pricing\Application\Actions\BulkUpdatePrices;
use Modules\Pricing\Application\Actions\CreatePriceList;
use Modules\Pricing\Application\Actions\QuotePrices;
use Modules\Pricing\Application\Actions\ReplaceProductPricing;
use Modules\Pricing\Application\Actions\SchedulePriceList;
use Modules\Pricing\Application\Actions\SetRepDiscountCap;
use Modules\Pricing\Domain\Models\PriceChangeLog;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Presentation\Http\Requests\BulkUpdatePricesRequest;
use Modules\Pricing\Presentation\Http\Requests\QuoteRequest;
use Modules\Pricing\Presentation\Http\Requests\ReplaceProductPricingRequest;
use Modules\Pricing\Presentation\Http\Requests\SchedulePriceListRequest;
use Modules\Pricing\Presentation\Http\Requests\SetRepDiscountCapRequest;
use Modules\Pricing\Presentation\Http\Requests\StorePriceListRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class PricingController extends ApiController
{
    public function lists(Request $request): JsonResponse
    {
        $page = QueryBuilder::for(PriceList::class)
            ->allowedFilters(AllowedFilter::exact('type'))
            ->defaultSort('-created_at')
            ->paginate(min((int) $request->get('per_page', 25), 100));

        return $this->paginated($page, fn (PriceList $list) => [
            'id' => (int) $list->id,
            'name' => $list->name,
            'type' => $list->type->value,
            'status' => $list->status->value,
        ]);
    }

    public function storeList(StorePriceListRequest $request, CreatePriceList $action): JsonResponse
    {
        return $this->created($action($request->user(), $request->validated()));
    }

    public function replaceProduct(ReplaceProductPricingRequest $request, ReplaceProductPricing $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function schedule(SchedulePriceListRequest $request, SchedulePriceList $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function bulk(BulkUpdatePricesRequest $request, BulkUpdatePrices $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function changeLog(Request $request): JsonResponse
    {
        $page = QueryBuilder::for(PriceChangeLog::class)
            ->allowedFilters(
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('user_id', 'actor_user_id'),
                AllowedFilter::callback('date', function ($query, $value): void {
                    $query->whereDate('at', $value);
                }),
            )
            ->defaultSort('-at')
            ->paginate(min((int) $request->get('per_page', 25), 100));

        return $this->paginated($page, fn (PriceChangeLog $log) => [
            'at' => $log->at?->timezone('Asia/Damascus')->toIso8601String(),
            'user' => $log->actor_user_id,
            'product' => $log->product_id,
            'before' => (int) $log->before,
            'after' => (int) $log->after,
            'reason' => $log->reason,
        ]);
    }

    public function discountCap(SetRepDiscountCapRequest $request, SetRepDiscountCap $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function quote(QuoteRequest $request, QuotePrices $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }
}
