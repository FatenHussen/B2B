<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final class ChannelManagerInvited
{
    public function __construct(
        public readonly int $channelId,
        public readonly string $name,
        public readonly string $phone,
        public readonly ?string $email,
        public readonly string $inviteVia,
    ) {}
}
