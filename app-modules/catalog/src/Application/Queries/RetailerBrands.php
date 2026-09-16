<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Catalog\Application\Support\VisibleCatalogQuery;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Support\MediaUrl;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RetailerBrands
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly ReferenceDirectory $refs,
    ) {}

    public function paginate(object $user, Request $request): LengthAwarePaginator
    {
        $ctx = $this->shopping->for($user);
        $channelIds = $ctx['channel_ids'] === [] ? [0] : $ctx['channel_ids'];

        // Lifted, per rule 10: no tenant on /app/retailer/*; the retailer's channels are the
        // filter on the next line.
        $base = Brand::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $channelIds)
            ->where('status', BrandStatus::Active);

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callback('activity_type_id', function ($query, $value): void {
                    $query->whereHas('activityTypes', fn ($q) => $q->where('activity_type_id', $value));
                }),
            )
            ->defaultSort('order')
            ->paginate(min((int) $request->get('per_page', 25), 100));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(object $user, int $id): array
    {
        $ctx = $this->shopping->for($user);
        $brand = Brand::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $ctx['channel_ids'] === [] ? [0] : $ctx['channel_ids'])
            ->with('activityTypes')
            ->find($id);

        if ($brand === null) {
            throw new NotFoundHttpException;
        }

        $activities = [];
        foreach ($brand->activityTypes as $row) {
            $activities[] = [
                'id' => (int) $row->activity_type_id,
                'name' => $this->refs->activityTypeName((int) $row->activity_type_id) ?? '',
            ];
        }

        $categories = VisibleCatalogQuery::products($ctx)
            ->where('brand_id', $brand->id)
            ->with('category')
            ->get()
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])
            ->values()
            ->all();

        return [
            'banner' => MediaUrl::of($brand->banner_media_id ? (int) $brand->banner_media_id : null),
            'logo' => MediaUrl::of($brand->logo_media_id ? (int) $brand->logo_media_id : null),
            'name' => (string) $brand->name_ar,
            'activities' => $activities,
            'description' => $brand->description,
            'categories' => $categories,
            'sliders' => [
                ['key' => 'best_selling', 'items' => []],
            ],
        ];
    }
}
