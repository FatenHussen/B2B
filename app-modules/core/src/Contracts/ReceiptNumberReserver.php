<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ReceiptNumberReserver
{
    public function reserve(int $channelId): string;

    /**
     * Mint a receipt number and bind it to this rep for 24 hours (EP-CM-050, REQ-IN-01).
     */
    public function reserveFor(int $channelId, int $repUserId): string;
}
