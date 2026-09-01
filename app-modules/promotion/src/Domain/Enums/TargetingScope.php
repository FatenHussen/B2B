<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Enums;

enum TargetingScope: string
{
    case All = 'all';
    case Zones = 'zones';
    case Groups = 'groups';
    case Retailers = 'retailers';
}
