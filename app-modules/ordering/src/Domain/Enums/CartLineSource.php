<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Enums;

enum CartLineSource: string
{
    case Browse = 'browse';
    case Shortage = 'shortage';
    case Favorite = 'favorite';
    case Reorder = 'reorder';
    case Offer = 'offer';
}
