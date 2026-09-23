<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ChannelUserDirectory
{
    /**
     * @return list<array{id: int, name: string, phone: string|null, role: string|null, status: string, last_login_at: string|null}>
     */
    public function listForChannel(int $channelId): array;
}
