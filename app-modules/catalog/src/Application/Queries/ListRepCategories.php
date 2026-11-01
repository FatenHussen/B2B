<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Modules\Catalog\Application\Support\RepCategoryIndex;
use Modules\Core\Contracts\RepSellingContext;

final class ListRepCategories
{
    public function __construct(
        private readonly RepSellingContext $selling,
        private readonly RepCategoryIndex $index,
    ) {}

    /**
     * @return list<array{id: int, name: string, root_category_id: int}>
     */
    public function __invoke(object $user, ?int $rootId): array
    {
        if ($rootId === null || $rootId <= 0) {
            return [];
        }

        $ctx = $this->selling->for($user);

        return $this->index->underRoot($ctx['channel_ids'], $rootId);
    }
}
