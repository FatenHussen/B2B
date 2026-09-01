<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum PriceType: string
{
    case Simple = 'simple';
    case Tiered = 'tiered';
}
