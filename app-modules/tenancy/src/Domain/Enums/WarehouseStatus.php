<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Enums;

enum WarehouseStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
