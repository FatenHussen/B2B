<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Enums;

enum AppUserKind: string
{
    case Retailer = 'retailer';
    case Rep = 'rep';
}
