<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Modules\Catalog\Domain\Enums\CategoryStatus;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;

final class ChangeCategoryStatus
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @return array{id: int, status: string}
     */
    public function __invoke(object $actor, int $id, string $status, string $reason): array
    {
        $category = Category::query()->find($id);
        if ($category === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        $to = CategoryStatus::tryFrom($status);
        if ($to === null) {
            throw DomainException::of(ErrorCode::ValidationFailed, __('catalog.category_status_invalid'));
        }

        if ($to === CategoryStatus::Disabled) {
            $scopeIds = $this->descendantIdsIncludingSelf((int) $category->id);
            $childCategories = Category::query()
                ->where('parent_id', $category->id)
                ->where('status', '!=', CategoryStatus::Disabled)
                ->count();
            $activeProducts = Product::query()
                ->whereIn('category_id', $scopeIds)
                ->where('status', ProductStatus::Active)
                ->count();

            if ($childCategories > 0 || $activeProducts > 0) {
                throw DomainException::of(ErrorCode::RefInUse, __('catalog.category_in_use'), [
                    'affected' => [
                        'child_categories' => $childCategories,
                        'active_products' => $activeProducts,
                    ],
                ]);
            }
        }

        $from = $category->status->value;
        $category->forceFill(['status' => $to])->save();

        $this->audit->record('catalog.category.status', $actor, 'category', (int) $category->id, [
            'before' => ['status' => $from],
            'after' => ['status' => $to->value],
            'reason' => $reason,
        ], Tenant::currentId());

        return ['id' => (int) $category->id, 'status' => $to->value];
    }

    /**
     * @return list<int>
     */
    private function descendantIdsIncludingSelf(int $rootId): array
    {
        $all = Category::query()->get(['id', 'parent_id']);
        $ids = [$rootId];
        $frontier = [$rootId];
        while ($frontier !== []) {
            $children = $all
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique([...$ids, ...$children]));
        }

        return $ids;
    }
}
