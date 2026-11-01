<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Catalog\Domain\Enums\ProductMediaRole;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductMedia;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\ChannelUserDirectory;
use Modules\Core\Contracts\ProductPricingReader;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\MediaUrl;
use Modules\Core\Support\Tenant;

/**
 * Numbers for the channel catalog overview. Every figure is a count of rows
 * this channel owns. Month-over-month is the growth of rows that still exist:
 * (now − rows created before this calendar month) / that earlier count.
 * A channel whose whole catalog was created this month has a null change,
 * because there is no earlier count to divide by.
 */
final class CatalogSummary
{
    private const RECENT = 8;

    /** @var list<string> */
    private const CHANGE_ACTIONS = [
        'catalog.product.create',
        'catalog.product.update',
        'catalog.product.duplicate',
        'catalog.product.bulk',
        'catalog.brand.create',
        'catalog.brand.update',
        'catalog.category.create',
        'catalog.category.update',
        'catalog.category.status',
        'catalog.category.reorder',
        'catalog.variant.update',
        'catalog.variant.delete',
        'catalog.variants.generate',
        'catalog.import',
        'pricing.product.update',
        'pricing.bulk',
    ];

    public function __construct(
        private readonly ProductPricingReader $pricing,
        private readonly ReferenceDirectory $refs,
        private readonly AuditTrail $audit,
        private readonly ChannelUserDirectory $users,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $start = now()->startOfMonth();
        $products = $this->productCounts($start);
        $categories = $this->entityCounts(Category::query(), $start);
        $brands = $this->entityCounts(Brand::query(), $start);

        return [
            'counts' => [
                'products' => $products['total'],
                'products_change_pct' => $this->changePct($products['total'], $products['before_month']),
                'categories' => $categories['total'],
                'categories_change_pct' => $this->changePct($categories['total'], $categories['before_month']),
                'brands' => $brands['total'],
                'brands_change_pct' => $this->changePct($brands['total'], $brands['before_month']),
                'active' => $products['active'],
                'active_share_pct' => $this->sharePct($products['active'], $products['total']),
                'stopped' => $products['stopped'],
                'stopped_share_pct' => $this->sharePct($products['stopped'], $products['total']),
            ],
            'by_category' => $this->byCategory(),
            'by_brand' => $this->byBrand(),
            'by_status' => $this->byStatus($products),
            'recent_products' => $this->recentProducts(),
            'recent_changes' => $this->recentChanges(),
        ];
    }

    /**
     * @return array{total: int, before_month: int, active: int, stopped: int, draft: int}
     */
    private function productCounts(Carbon $start): array
    {
        $row = Product::query()
            ->selectRaw(
                'COUNT(*) as total,
                 SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) as before_month,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_count,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as stopped_count,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft_count',
                [$start, ProductStatus::Active->value, ProductStatus::Disabled->value, ProductStatus::Draft->value],
            )
            ->first();

        return [
            'total' => (int) ($row?->getAttribute('total') ?? 0),
            'before_month' => (int) ($row?->getAttribute('before_month') ?? 0),
            'active' => (int) ($row?->getAttribute('active_count') ?? 0),
            'stopped' => (int) ($row?->getAttribute('stopped_count') ?? 0),
            'draft' => (int) ($row?->getAttribute('draft_count') ?? 0),
        ];
    }

    /**
     * @param  Builder<Brand>| Builder<Category>  $query
     * @return array{total: int, before_month: int}
     */
    private function entityCounts($query, Carbon $start): array
    {
        $row = $query
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) as before_month',
                [$start],
            )
            ->first();

        return [
            'total' => (int) ($row?->getAttribute('total') ?? 0),
            'before_month' => (int) ($row?->getAttribute('before_month') ?? 0),
        ];
    }

    /**
     * Products roll up to the platform root they hang under, or to the channel's
     * own top category when the node is not under a platform root.
     *
     * @return list<array{bucket: string, id: int|null, name: string, count: int}>
     */
    private function byCategory(): array
    {
        /** @var array<int, Category> $categories */
        $categories = [];
        foreach (Category::query()->get() as $category) {
            $categories[(int) $category->id] = $category;
        }

        /** @var array<string, array{bucket: string, id: int|null, name: string, count: int}> $buckets */
        $buckets = [];

        $grouped = Product::query()
            ->select('category_id')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->get();

        foreach ($grouped as $row) {
            $count = (int) $row->getAttribute('aggregate');
            $categoryId = $row->getAttribute('category_id');
            $category = $categoryId === null ? null : ($categories[(int) $categoryId] ?? null);
            $bucket = $this->categoryBucket($category, $categories);
            $key = $bucket['bucket'].':'.($bucket['id'] ?? 'none');
            if (! isset($buckets[$key])) {
                $buckets[$key] = $bucket;
            }
            $buckets[$key]['count'] += $count;
        }

        $list = array_values($buckets);
        usort($list, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $list;
    }

    /**
     * @param  array<int, Category>  $categories
     * @return array{bucket: string, id: int|null, name: string, count: int}
     */
    private function categoryBucket(?Category $category, array $categories): array
    {
        if ($category === null) {
            return ['bucket' => 'unassigned', 'id' => null, 'name' => 'بدون فئة', 'count' => 0];
        }

        $rootId = $category->root_category_id !== null ? (int) $category->root_category_id : null;
        if ($rootId !== null && $category->parent_id === null && (int) $category->level === 2) {
            $name = $this->refs->rootCategoryName($rootId) ?? 'فئة رئيسية';

            return ['bucket' => 'root', 'id' => $rootId, 'name' => $name, 'count' => 0];
        }

        if ($rootId !== null) {
            $walk = $category;
            $guard = 0;
            while ($walk->parent_id !== null && isset($categories[(int) $walk->parent_id]) && $guard < 8) {
                $walk = $categories[(int) $walk->parent_id];
                $guard++;
            }
            if ($walk->parent_id === null && $walk->root_category_id !== null) {
                $name = $this->refs->rootCategoryName((int) $walk->root_category_id) ?? 'فئة رئيسية';

                return ['bucket' => 'root', 'id' => (int) $walk->root_category_id, 'name' => $name, 'count' => 0];
            }
        }

        $top = $category;
        $guard = 0;
        while ($top->parent_id !== null && isset($categories[(int) $top->parent_id]) && $guard < 8) {
            $top = $categories[(int) $top->parent_id];
            $guard++;
        }

        return ['bucket' => 'category', 'id' => (int) $top->id, 'name' => (string) $top->name, 'count' => 0];
    }

    /**
     * @return list<array{id: int|null, name: string, count: int}>
     */
    private function byBrand(): array
    {
        $grouped = Product::query()
            ->select('brand_id')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('brand_id')
            ->get();

        $ids = [];
        foreach ($grouped as $row) {
            $brandId = $row->getAttribute('brand_id');
            if ($brandId !== null) {
                $ids[] = (int) $brandId;
            }
        }

        $names = $ids === []
            ? []
            : Brand::query()->whereIn('id', $ids)->pluck('name_ar', 'id')->all();

        $list = [];
        foreach ($grouped as $row) {
            $brandId = $row->getAttribute('brand_id');
            $id = $brandId === null ? null : (int) $brandId;
            $list[] = [
                'id' => $id,
                'name' => $id === null ? 'بدون علامة' : (string) ($names[$id] ?? 'براند محذوف'),
                'count' => (int) $row->getAttribute('aggregate'),
            ];
        }

        usort($list, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $list;
    }

    /**
     * @param  array{total: int, before_month: int, active: int, stopped: int, draft: int}  $products
     * @return list<array{status: string, count: int}>
     */
    private function byStatus(array $products): array
    {
        $rows = [
            ['status' => ProductStatus::Active->value, 'count' => $products['active']],
            ['status' => ProductStatus::Disabled->value, 'count' => $products['stopped']],
            ['status' => ProductStatus::Draft->value, 'count' => $products['draft']],
        ];

        return array_values(array_filter($rows, fn (array $row): bool => $row['count'] > 0));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentProducts(): array
    {
        $products = Product::query()->orderByDesc('created_at')->orderByDesc('id')->limit(self::RECENT)->get();
        if ($products->isEmpty()) {
            return [];
        }

        $ids = $products->map(fn (Product $product): int => (int) $product->id)->all();
        $brandIds = $products->pluck('brand_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $categoryIds = $products->pluck('category_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();

        $brandNames = $brandIds === [] ? [] : Brand::query()->whereIn('id', $brandIds)->pluck('name_ar', 'id')->all();
        $categoryNames = $categoryIds === [] ? [] : Category::query()->whereIn('id', $categoryIds)->pluck('name', 'id')->all();

        $media = ProductMedia::query()
            ->whereIn('product_id', $ids)
            ->orderBy('order')
            ->get()
            ->groupBy(fn (ProductMedia $row): int => (int) $row->product_id);

        $rows = [];
        foreach ($products as $product) {
            $id = (int) $product->id;
            $priced = $this->pricing->show($id);
            $image = $this->primaryImage($media->get($id));
            $brandId = $product->brand_id !== null ? (int) $product->brand_id : null;
            $categoryId = $product->category_id !== null ? (int) $product->category_id : null;

            $rows[] = [
                'id' => $id,
                'name_ar' => (string) $product->name_ar,
                'brand' => $brandId !== null ? (isset($brandNames[$brandId]) ? (string) $brandNames[$brandId] : null) : null,
                'category' => $categoryId !== null ? (isset($categoryNames[$categoryId]) ? (string) $categoryNames[$categoryId] : null) : null,
                'price' => $priced['base_price'] ?? null,
                'currency_id' => $priced['currency_id'] ?? null,
                'status' => $product->status->value,
                'created_at' => $product->created_at?->toIso8601String(),
                'image' => $image,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, ProductMedia>|null  $rows
     */
    private function primaryImage($rows): ?string
    {
        if ($rows === null || $rows->isEmpty()) {
            return null;
        }

        $primary = $rows->first(fn (ProductMedia $row): bool => $row->role === ProductMediaRole::Primary)
            ?? $rows->first(fn (ProductMedia $row): bool => $row->role === ProductMediaRole::Image);

        return $primary instanceof ProductMedia ? MediaUrl::of((int) $primary->media_id) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentChanges(): array
    {
        $channelId = Tenant::currentId();
        if ($channelId === null) {
            return [];
        }

        $entries = $this->audit->recent([
            'channel_id' => $channelId,
            'action_any' => self::CHANGE_ACTIONS,
        ], self::RECENT);

        $actorNames = [];
        foreach ($this->users->listForChannel($channelId) as $user) {
            $actorNames[$user['id']] = $user['name'];
        }

        $names = $this->subjectNames($entries);

        $rows = [];
        foreach ($entries as $entry) {
            $after = $entry['properties']['after'] ?? null;
            $bulk = is_array($after) && isset($after['action']) && is_string($after['action']) ? $after['action'] : null;
            $type = $entry['entity_type'];
            $subjectId = $entry['entity_id'];
            $key = ($type ?? '').':'.($subjectId ?? '');

            $rows[] = [
                'at' => $entry['at'],
                'action' => (string) ($entry['action'] ?? ''),
                'bulk_action' => $bulk,
                'subject_type' => $type,
                'subject_id' => $subjectId,
                'subject_name' => $names[$key] ?? null,
                'actor_name' => $entry['actor'] !== null ? ($actorNames[$entry['actor']] ?? null) : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{at: string|null, actor: int|null, action: string|null, entity_type: string|null, entity_id: int|null, ip: string|null, properties: array<string, mixed>}>  $entries
     * @return array<string, string>
     */
    private function subjectNames(array $entries): array
    {
        $productIds = [];
        $brandIds = [];
        $categoryIds = [];
        $variantIds = [];

        foreach ($entries as $entry) {
            $id = $entry['entity_id'];
            if ($id === null) {
                continue;
            }
            match ($entry['entity_type']) {
                'product' => $productIds[] = $id,
                'brand' => $brandIds[] = $id,
                'category' => $categoryIds[] = $id,
                'product_variant' => $variantIds[] = $id,
                default => null,
            };
        }

        $names = [];
        if ($productIds !== []) {
            foreach (Product::query()->whereIn('id', $productIds)->pluck('name_ar', 'id') as $id => $name) {
                $names['product:'.$id] = (string) $name;
            }
        }
        if ($brandIds !== []) {
            foreach (Brand::query()->whereIn('id', $brandIds)->pluck('name_ar', 'id') as $id => $name) {
                $names['brand:'.$id] = (string) $name;
            }
        }
        if ($categoryIds !== []) {
            foreach (Category::query()->whereIn('id', $categoryIds)->pluck('name', 'id') as $id => $name) {
                $names['category:'.$id] = (string) $name;
            }
        }
        if ($variantIds !== []) {
            $variants = ProductVariant::query()->whereIn('id', $variantIds)->get(['id', 'product_id']);
            $ownerIds = $variants->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
            $owners = $ownerIds === [] ? [] : Product::query()->whereIn('id', $ownerIds)->pluck('name_ar', 'id')->all();
            foreach ($variants as $variant) {
                $owner = $owners[(int) $variant->product_id] ?? null;
                if ($owner !== null) {
                    $names['product_variant:'.$variant->id] = (string) $owner;
                }
            }
        }

        return $names;
    }

    private function changePct(int $now, int $then): ?int
    {
        if ($then < 1) {
            return null;
        }

        return (int) round((($now - $then) / $then) * 100);
    }

    private function sharePct(int $part, int $total): ?int
    {
        if ($total < 1) {
            return null;
        }

        return (int) round(($part / $total) * 100);
    }
}
