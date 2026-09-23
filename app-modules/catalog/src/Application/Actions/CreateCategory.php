<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Enums\CategoryStatus;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\CategoryActivityType;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class CreateCategory
{
    public function __construct(
        private readonly ReferenceDirectory $refs,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int}
     */
    public function __invoke(object $actor, array $data): array
    {
        $activityIds = array_map('intval', $data['activity_type_ids'] ?? []);
        if (! $this->refs->allActivityTypesExist($activityIds)) {
            InvalidFields::throw(['activity_type_ids' => 'catalog.activity_type_not_found']);
        }

        $parentId = array_key_exists('parent_id', $data) && $data['parent_id'] !== null
            ? (int) $data['parent_id']
            : null;
        [$level, $storedParent, $rootId] = $this->resolveParent($parentId);

        if ($level > 5) {
            InvalidFields::throw(['parent_id' => 'catalog.category_level']);
        }

        $category = DB::transaction(function () use ($data, $activityIds, $actor, $level, $storedParent, $rootId): Category {
            $category = Category::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'parent_id' => $storedParent,
                'root_category_id' => $rootId,
                'image_media_id' => isset($data['image']) && $data['image'] !== null && $data['image'] !== ''
                    ? (int) $data['image']
                    : null,
                'icon' => $data['icon'] ?? null,
                'order' => (int) ($data['order'] ?? 0),
                'status' => CategoryStatus::Active,
                'level' => $level,
            ]);

            foreach ($activityIds as $id) {
                CategoryActivityType::query()->create([
                    'category_id' => $category->id,
                    'activity_type_id' => $id,
                ]);
            }

            $this->audit->record('catalog.category.create', $actor, 'category', (int) $category->id, [
                'after' => ['name' => $category->name, 'level' => $level],
            ], Tenant::currentId());

            return $category;
        });

        return ['id' => (int) $category->id];
    }

    /**
     * @return array{0: int, 1: int|null, 2: int|null}
     */
    private function resolveParent(?int $parentId): array
    {
        // Channel-owned root: appears beside platform roots in the tree.
        if ($parentId === null) {
            return [1, null, null];
        }

        $parent = Category::query()->find($parentId);
        if ($parent !== null) {
            return [
                (int) $parent->level + 1,
                (int) $parent->id,
                $parent->root_category_id ? (int) $parent->root_category_id : (
                    (int) $parent->level === 1 && $parent->parent_id === null
                        ? (int) $parent->id
                        : null
                ),
            ];
        }

        if ($this->refs->rootCategoryExists($parentId)) {
            return [2, null, $parentId];
        }

        InvalidFields::throw(['parent_id' => 'catalog.parent_not_found']);
    }
}
