<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum AdjustmentMode: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
