<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Models\Category;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class ReorderCategories
{
    public function __construct(
        private readonly ReferenceDirectory $refs,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{moves: list<array{id: int, parent_id: int|null, order: int}>}  $data
     * @return array{updated: int}
     */
    public function __invoke(object $actor, array $data): array
    {
        $moves = $data['moves'] ?? [];

        $updated = DB::transaction(function () use ($moves): int {
            $count = 0;
            foreach ($moves as $move) {
                $category = Category::query()->find((int) $move['id']);
                if ($category === null) {
                    InvalidFields::throw(['moves' => 'catalog.category_not_found']);
                }

                $parentId = isset($move['parent_id']) ? (int) $move['parent_id'] : null;
                [$level, $storedParent, $rootId] = $this->resolveParent((int) $category->id, $parentId);

                if ($level > 5) {
                    InvalidFields::throw(['moves' => 'catalog.category_level']);
                }

                $category->forceFill([
                    'parent_id' => $storedParent,
                    'root_category_id' => $rootId,
                    'order' => (int) $move['order'],
                    'level' => $level,
                ])->save();
                $count++;
            }

            return $count;
        });

        $this->audit->record('catalog.category.reorder', $actor, 'category', null, [
            'after' => ['updated' => $updated],
        ], Tenant::currentId());

        return ['updated' => $updated];
    }

    /**
     * @return array{0: int, 1: int|null, 2: int|null}
     */
    private function resolveParent(int $id, ?int $parentId): array
    {
        if ($parentId === null || $parentId === 0) {
            InvalidFields::throw(['moves' => 'catalog.parent_required']);
        }

        if ($parentId === $id) {
            InvalidFields::throw(['moves' => 'catalog.category_cycle']);
        }

        if ($this->refs->rootCategoryExists($parentId)) {
            return [2, null, $parentId];
        }

        $parent = Category::query()->find($parentId);
        if ($parent === null) {
            InvalidFields::throw(['moves' => 'catalog.parent_not_found']);
        }

        $cursor = $parent;
        while ($cursor !== null) {
            if ((int) $cursor->id === $id) {
                InvalidFields::throw(['moves' => 'catalog.category_cycle']);
            }
            $cursor = $cursor->parent_id ? Category::query()->find($cursor->parent_id) : null;
        }

        return [
            (int) $parent->level + 1,
            (int) $parent->id,
            $parent->root_category_id ? (int) $parent->root_category_id : null,
        ];
    }
}
