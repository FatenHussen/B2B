<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Http\Request;
use Modules\Catalog\Application\Support\VisibleCatalogQuery;
use Modules\Catalog\Domain\Models\Category;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Support\MediaUrl;

final class ListRetailerCategories
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly ReferenceDirectory $refs,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(object $user, Request $request): array
    {
        $ctx = $this->shopping->for($user);
        $parentId = $request->integer('parent_id') ?: null;
        $level = $request->integer('level') ?: null;

        if ($parentId === null && ($level === null || $level === 1)) {
            return array_map(function (array $c) use ($ctx): array {
                $children = Category::withoutGlobalScope('channel')
                    ->whereIn('supply_channel_id', $ctx['channel_ids'] === [] ? [0] : $ctx['channel_ids'])
                    ->where('root_category_id', $c['id'])
                    ->whereNull('parent_id')
                    ->count();
                $products = VisibleCatalogQuery::products($ctx)
                    ->whereHas('category', fn ($q) => $q->withoutGlobalScope('channel')->where('root_category_id', $c['id']))
                    ->count();

                return [
                    'id' => $c['id'],
                    'name' => $c['name'],
                    'image' => $c['image'],
                    'children_count' => $children,
                    'products_count' => $products,
                ];
            }, array_values(array_filter(
                $this->refs->activeRootCategories(),
                fn (array $row) => $ctx['category_ids'] === [] || in_array($row['id'], $ctx['category_ids'], true),
            )));
        }

        $query = Category::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $ctx['channel_ids'] === [] ? [0] : $ctx['channel_ids']);

        if ($parentId && $this->refs->rootCategoryExists($parentId)) {
            $query->where('root_category_id', $parentId)->whereNull('parent_id');
        } elseif ($parentId) {
            $query->where('parent_id', $parentId);
        }

        return $query->orderBy('order')->get()->map(function (Category $c) use ($ctx): array {
            return [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'image' => MediaUrl::of($c->image_media_id ? (int) $c->image_media_id : null),
                'children_count' => Category::withoutGlobalScope('channel')->where('parent_id', $c->id)->count(),
                'products_count' => VisibleCatalogQuery::products($ctx)->where('category_id', $c->id)->count(),
            ];
        })->all();
    }
}
