<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Enums;

enum PlanStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
