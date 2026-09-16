<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface RepSellingContext
{
    /**
     * Is this authenticated actor an app field rep?
     *
     * Promotion's offer feed is a shared `/app/offers` route: retailers and reps both
     * call it, and the feed has to know which context to resolve without asking Identity
     * for the user table.
     */
    public function isRep(object $user): bool;

    /**
     * @return array{
     *     rep_id: int,
     *     channel_ids: list<int>,
     *     default_zone_id: int|null,
     *     activity_type_id: int|null
     * }
     */
    public function for(object $user): array;
}
