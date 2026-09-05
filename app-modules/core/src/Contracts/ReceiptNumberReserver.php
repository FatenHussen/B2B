<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ReceiptNumberReserver
{
    public function reserve(int $channelId): string;
}
