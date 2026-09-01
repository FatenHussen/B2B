<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface RetailerShoppingContext
{
    /**
     * @return array{
     *     retailer_id: int,
     *     zone_id: int,
     *     zone_name: string,
     *     activity_type_id: int,
     *     category_ids: list<int>,
     *     channel_ids: list<int>,
     *     shop_name: string,
     *     status: string
     * }
     */
    public function for(object $user): array;
}
