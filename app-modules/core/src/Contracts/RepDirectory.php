<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface RepDirectory
{
    public function exists(int $repUserId): bool;

    public function belongsToChannel(int $repUserId, int $channelId): bool;
}
