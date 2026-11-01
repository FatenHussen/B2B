<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Support;

use Modules\Catalog\Domain\Enums\CategoryStatus;
use Modules\Catalog\Domain\Models\Category;
use Modules\Core\Support\Tenant;

/**
 * The rep filter sheet has two category dropdowns. The root comes from public
 * refs. The subcategory is a channel category whose parent was that root, so
 * its parent_id is null and root_category_id is the ref.
 */
final class RepCategoryIndex
{
    /**
     * @param  list<int>  $channelIds
     * @return list<array{id: int, name: string, root_category_id: int}>
     */
    public function underRoot(array $channelIds, int $rootId): array
    {
        $rows = [];
        foreach ($this->categories($channelIds) as $category) {
            if ($category->status !== CategoryStatus::Active) {
                continue;
            }
            if ($category->parent_id !== null || (int) $category->root_category_id !== $rootId) {
                continue;
            }
            $rows[] = [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'root_category_id' => $rootId,
            ];
        }

        return $rows;
    }

    /**
     * The chosen subcategory plus every category nested under it.
     *
     * @param  list<int>  $channelIds
     * @return list<int>
     */
    public function branch(array $channelIds, int $categoryId): array
    {
        $byParent = [];
        foreach ($this->categories($channelIds) as $category) {
            $parent = $category->parent_id === null ? 0 : (int) $category->parent_id;
            $byParent[$parent][] = (int) $category->id;
        }

        $ids = [];
        $seen = [];
        $stack = [$categoryId];
        while ($stack !== []) {
            $id = array_pop($stack);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $ids[] = $id;
            foreach ($byParent[$id] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        return $ids;
    }

    /**
     * @param  list<int>  $channelIds
     * @return list<Category>
     */
    private function categories(array $channelIds): array
    {
        $rows = [];
        foreach ($channelIds as $channelId) {
            if ($channelId <= 0) {
                continue;
            }
            $found = Tenant::as($channelId, fn (): array => Category::query()->get()->all());
            array_push($rows, ...$found);
        }

        return $rows;
    }
}
