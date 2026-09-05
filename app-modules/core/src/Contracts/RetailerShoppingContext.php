<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface RetailerShoppingContext
{
    /**
     * Is this authenticated actor an app retailer?
     *
     * Callers outside Identity cannot answer this themselves: it takes both the user
     * table the token belongs to and the kind recorded on it, and neither is theirs to
     * read. `for()` throws on anything else, so this is the question to ask first when a
     * non-retailer is a legitimate case rather than an error.
     */
    public function isRetailer(object $user): bool;

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
