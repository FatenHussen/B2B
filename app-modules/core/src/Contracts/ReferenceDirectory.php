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
}
