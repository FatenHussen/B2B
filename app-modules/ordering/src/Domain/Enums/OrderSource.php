<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Enums;

enum OrderSource: string
{
    case RetailerApp = 'retailer_app';
    case RepApp = 'rep_app';
}
