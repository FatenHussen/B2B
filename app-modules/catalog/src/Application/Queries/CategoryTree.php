<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Support\Collection;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\MediaUrl;

final class CategoryTree
{
    public function __construct(private readonly ReferenceDirectory $refs) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(): array
    {
        $categories = Category::query()->orderBy('order')->orderBy('id')->get();
        $byParent = $categories->groupBy(fn (Category $c) => $c->parent_id === null
            ? 'root-'.($c->root_category_id ?? 'channel-'.$c->id)
            : 'cat-'.$c->parent_id);

        $nodes = [];
        foreach ($this->refs->activeRootCategories() as $root) {
            $children = $byParent->get('root-'.$root['id'], collect())
                ->map(fn (Category $c) => $this->node($c, $byParent))
                ->values()
                ->all();

            $nodes[] = [
                'id' => $root['id'],
                'name' => $root['name'],
                'image' => $root['image'],
                'icon' => $root['icon'],
                'parent_id' => null,
                'level' => 1,
                'order' => $root['order'],
                'status' => 'active',
                'description' => null,
                'direct_products' => 0,
                'total_products' => $this->sumProducts($children),
                'children' => $children,
            ];
        }

        foreach ($categories->filter(fn (Category $c) => (int) $c->level === 1 && $c->parent_id === null && $c->root_category_id === null) as $channelRoot) {
            $nodes[] = $this->node($channelRoot, $byParent);
        }

        return $nodes;
    }

    /**
     * @param  Collection<string, Collection<int, Category>>  $byParent
     * @return array<string, mixed>
     */
    private function node(Category $category, $byParent): array
    {
        $children = $byParent->get('cat-'.$category->id, collect())
            ->map(fn (Category $c) => $this->node($c, $byParent))
            ->values()
            ->all();

        $direct = Product::query()->where('category_id', $category->id)->count();

        return [
            'id' => (int) $category->id,
            'name' => (string) $category->name,
            'description' => $category->description,
            'image' => MediaUrl::of($category->image_media_id ? (int) $category->image_media_id : null),
            'icon' => $category->icon,
            'parent_id' => $category->parent_id ? (int) $category->parent_id : $category->root_category_id,
            'level' => (int) $category->level,
            'order' => (int) $category->order,
            'status' => $category->status->value,
            'direct_products' => $direct,
            'total_products' => $direct + $this->sumProducts($children),
            'children' => $children,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $children
     */
    private function sumProducts(array $children): int
    {
        return array_sum(array_map(fn (array $n) => (int) $n['total_products'], $children));
    }
}
