<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * A platform reference entity was disabled (BE-R01).
 *
 * Notifies, never controls: Catalog, Tenancy and Ordering may listen to stop *new* use of
 * the entity, and nothing they do flows back here. Existing rows that point at the
 * entity are left exactly as they were — disabling is logical, never a delete (rule 12).
 *
 * `$entity` is the catalog's plural slug: `governorates`, `zones`, `activity_types`,
 * `root_categories`, `sale_units`, `equipments`, `currencies`.
 */
final class ReferenceDisabled
{
    public function __construct(
        public readonly string $entity,
        public readonly int $id,
        public readonly string $reason,
    ) {}
}
