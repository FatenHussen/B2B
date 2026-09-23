<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Enums\CategoryStatus;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\CategoryActivityType;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class UpdateCategory
{
    public function __construct(
        private readonly ReferenceDirectory $refs,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int}
     */
    public function __invoke(object $actor, int $id, array $data): array
    {
        $category = Category::query()->find($id);
        if ($category === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        $activityIds = array_map('intval', $data['activity_type_ids'] ?? []);
        if (! $this->refs->allActivityTypesExist($activityIds)) {
            InvalidFields::throw(['activity_type_ids' => 'catalog.activity_type_not_found']);
        }

        DB::transaction(function () use ($category, $data, $activityIds, $actor): void {
            $category->forceFill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'image_media_id' => array_key_exists('image', $data) && $data['image'] !== null && $data['image'] !== ''
                    ? (int) $data['image']
                    : null,
                'icon' => $data['icon'] ?? null,
                'order' => (int) ($data['order'] ?? $category->order),
            ])->save();

            CategoryActivityType::query()->where('category_id', $category->id)->delete();
            foreach ($activityIds as $activityId) {
                CategoryActivityType::query()->create([
                    'category_id' => $category->id,
                    'activity_type_id' => $activityId,
                ]);
            }

            $this->audit->record('catalog.category.update', $actor, 'category', (int) $category->id, [
                'after' => ['name' => $category->name],
            ], Tenant::currentId());
        });

        return ['id' => (int) $category->id];
    }
}
