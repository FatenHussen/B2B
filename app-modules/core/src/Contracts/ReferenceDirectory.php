<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ReferenceDirectory
{
    public function zoneBelongsToGovernorate(int $zoneId, int $governorateId): bool;

    public function zoneIsActive(int $zoneId): bool;

    public function activityTypeExists(int $activityTypeId): bool;

    public function equipmentExists(int $equipmentId): bool;

    public function rootCategoryExists(int $categoryId): bool;

    /**
     * @param  list<int>  $ids
     */
    public function allEquipmentsExist(array $ids): bool;

    /**
     * @param  list<int>  $ids
     */
    public function allRootCategoriesExist(array $ids): bool;

    public function saleUnitExists(int $saleUnitId): bool;

    public function saleUnitName(int $saleUnitId): ?string;

    public function currencyExists(int $currencyId): bool;

    public function currencyCode(int $currencyId): ?string;

    public function defaultCurrencyId(): ?int;

    /**
     * @param  list<int>  $ids
     */
    public function allActivityTypesExist(array $ids): bool;

    /**
     * @param  list<int>  $ids
     */
    public function allZonesExist(array $ids): bool;

    public function activityTypeName(int $activityTypeId): ?string;

    public function zoneName(int $zoneId): ?string;

    public function zoneGovernorateId(int $zoneId): ?int;

    public function rootCategoryName(int $rootCategoryId): ?string;

    /**
     * @return list<array{id: int, name: string, image: string|null, icon: string|null, order: int}>
     */
    public function activeRootCategories(): array;
}
