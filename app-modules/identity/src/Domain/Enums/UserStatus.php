<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Pending = 'pending';
}
