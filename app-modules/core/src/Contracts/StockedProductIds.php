<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Tracked products that still have units to sell. Catalog uses this for the
 * rep filter «المتوفر فقط» and must not read stock tables.
 */
interface StockedProductIds
{
    /**
     * @param  list<int>  $channelIds
     * @return list<int>
     */
    public function ids(array $channelIds): array;
}
