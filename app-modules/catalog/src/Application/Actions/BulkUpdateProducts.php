<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class BulkUpdateProducts
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array{product_ids: list<int>, action: string}  $data
     * @return array{affected_count: int}
     */
    public function __invoke(object $actor, array $data): array
    {
        $ids = array_slice(array_map('intval', $data['product_ids'] ?? []), 0, 200);
        $action = (string) $data['action'];

        $query = Product::query()->whereIn('id', $ids);
        $affected = match ($action) {
            'activate' => $query->update(['status' => ProductStatus::Active->value]),
            'disable' => $query->update(['status' => ProductStatus::Disabled->value]),
            'delete_draft' => Product::query()
                ->whereIn('id', $ids)
                ->where('status', ProductStatus::Draft)
                ->delete(),
            default => InvalidFields::throw(['action' => 'catalog.bulk_action_invalid']),
        };

        $this->audit->record('catalog.product.bulk', $actor, 'product', null, [
            'after' => ['action' => $action, 'ids' => $ids, 'affected' => $affected],
        ], Tenant::currentId());

        return ['affected_count' => (int) $affected];
    }
}
