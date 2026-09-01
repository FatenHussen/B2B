<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Enums;

enum BrandStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
