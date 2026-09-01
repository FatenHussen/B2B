<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Enums;

enum RefStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
