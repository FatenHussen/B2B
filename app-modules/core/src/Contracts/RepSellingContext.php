<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface RepSellingContext
{
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
