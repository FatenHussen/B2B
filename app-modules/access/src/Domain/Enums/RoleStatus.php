<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Enums;

enum RoleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
}
