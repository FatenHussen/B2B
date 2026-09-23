<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductZone;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class BulkUpdateProducts
{
    public function __construct(
        private readonly RecordsAudit $audit,
        private readonly ReferenceDirectory $refs,
    ) {}

    /**
     * @param  array{product_ids: list<int>, action: string, payload?: array<string, mixed>}  $data
     * @return array{affected_count: int}
     */
    public function __invoke(object $actor, array $data): array
    {
        $ids = array_slice(array_map('intval', $data['product_ids'] ?? []), 0, 200);
        $action = (string) $data['action'];
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        $query = Product::query()->whereIn('id', $ids);
        $affected = match ($action) {
            'activate' => $query->update(['status' => ProductStatus::Active->value]),
            'disable' => $query->update(['status' => ProductStatus::Disabled->value]),
            'delete_draft' => Product::query()
                ->whereIn('id', $ids)
                ->where('status', ProductStatus::Draft)
                ->delete(),
            'set_status' => $this->setStatus($ids, $payload),
            'set_category' => $this->setCategory($ids, $payload),
            'set_brand' => $this->setBrand($ids, $payload),
            'set_zones' => $this->setZones($ids, $payload),
            default => InvalidFields::throw(['action' => 'catalog.bulk_action_invalid']),
        };

        $this->audit->record('catalog.product.bulk', $actor, 'product', null, [
            'after' => ['action' => $action, 'ids' => $ids, 'affected' => $affected],
        ], Tenant::currentId());

        return ['affected_count' => (int) $affected];
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $payload
     */
    private function setStatus(array $ids, array $payload): int
    {
        if (! isset($payload['status'])) {
            InvalidFields::throw(['payload.status' => 'catalog.bulk_status_required']);
        }
        $status = ProductStatus::tryFrom((string) $payload['status']);
        if ($status === null) {
            InvalidFields::throw(['payload.status' => 'catalog.bulk_status_required']);
        }

        return Product::query()->whereIn('id', $ids)->update(['status' => $status->value]);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $payload
     */
    private function setCategory(array $ids, array $payload): int
    {
        if (! isset($payload['category_id'])) {
            InvalidFields::throw(['payload.category_id' => 'catalog.bulk_category_required']);
        }
        $categoryId = (int) $payload['category_id'];
        if (Category::query()->whereKey($categoryId)->doesntExist()) {
            InvalidFields::throw(['payload.category_id' => 'catalog.category_not_found']);
        }

        return Product::query()->whereIn('id', $ids)->update(['category_id' => $categoryId]);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $payload
     */
    private function setBrand(array $ids, array $payload): int
    {
        if (! isset($payload['brand_id'])) {
            InvalidFields::throw(['payload.brand_id' => 'catalog.bulk_brand_required']);
        }
        $brandId = (int) $payload['brand_id'];
        if (Brand::query()->whereKey($brandId)->doesntExist()) {
            InvalidFields::throw(['payload.brand_id' => 'catalog.brand_not_found']);
        }

        return Product::query()->whereIn('id', $ids)->update(['brand_id' => $brandId]);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $payload
     */
    private function setZones(array $ids, array $payload): int
    {
        if (! isset($payload['zone_ids']) || ! is_array($payload['zone_ids'])) {
            InvalidFields::throw(['payload.zone_ids' => 'catalog.bulk_zones_required']);
        }
        $zoneIds = array_map('intval', $payload['zone_ids']);
        if (! $this->refs->allZonesExist($zoneIds)) {
            InvalidFields::throw(['payload.zone_ids' => 'catalog.zone_not_found']);
        }

        $affected = 0;
        foreach (Product::query()->whereIn('id', $ids)->get() as $product) {
            ProductZone::query()->where('product_id', $product->id)->delete();
            foreach ($zoneIds as $zoneId) {
                ProductZone::query()->create(['product_id' => $product->id, 'zone_id' => $zoneId]);
            }
            $affected++;
        }

        return $affected;
    }
}
