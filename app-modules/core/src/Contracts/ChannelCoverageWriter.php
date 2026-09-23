<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ChannelCoverageWriter
{
    /**
     * @param  list<int>  $zoneIds
     */
    public function replace(int $channelId, array $zoneIds): void;
}
