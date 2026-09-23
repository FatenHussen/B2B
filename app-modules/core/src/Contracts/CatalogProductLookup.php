<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface CatalogProductLookup
{
    public function exists(int $productId): bool;

    public function isActive(int $productId): bool;

    public function channelId(int $productId): ?int;

    public function name(int $productId): ?string;

    public function saleUnitName(int $productId): ?string;

    /**
     * Visible to a retailer shopping snapshot (zone / activity / covering channels).
     *
     * @param  array{zone_id: int, activity_type_id: int, channel_ids: list<int>, category_ids?: list<int>}  $shopping
     */
    public function isVisibleToRetailer(int $productId, array $shopping): bool;

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     sku: string,
     *     barcode: string|null,
     *     brand: string|null,
     *     channel_id: int,
     *     tracked: bool,
     *     reorder_point: int,
     *     min_order_qty: int,
     *     sale_unit: string|null,
     *     image: string|null
     * }|null
     */
    public function snapshot(int $productId, ?int $variantId = null): ?array;

    /**
     * @return array{product_id: int, variant_id: int|null}|null
     */
    public function findByBarcode(string $barcode): ?array;

    /**
     * Nullable unit-price override for a variant; null means inherit product pricing.
     */
    public function variantPriceOverride(?int $variantId): ?int;

    /**
     * Channel categories hanging off a platform root category, across every channel —
     * the `child_categories` half of the in-use check on EP-AD-043C (BE-R05). A number,
     * never a row: Reference renders a refusal and reads no catalog table.
     */
    public function countCategoriesUnderRoot(int $rootCategoryId): int;

    /**
     * Active products under a root category across every channel — the
     * `active_products` half of the same check.
     */
    public function countActiveProductsUnderRoot(int $rootCategoryId): int;

    /**
     * Products, any status, that sell in a sale unit across every channel —
     * `affected.products` on EP-AD-043D (BE-R06). A unit in use cannot be disabled.
     */
    public function countProductsUsingSaleUnit(int $saleUnitId): int;

    /**
     * Products a channel holds against its `skus` plan limit (EP-AD-056 `limit_usage`).
     */
    public function countInChannel(int $channelId): int;
}
