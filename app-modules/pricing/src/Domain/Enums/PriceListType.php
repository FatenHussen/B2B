<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum PriceListType: string
{
    case Base = 'base';
    case Zone = 'zone';
    case Group = 'group';
    case Retailer = 'retailer';
}
