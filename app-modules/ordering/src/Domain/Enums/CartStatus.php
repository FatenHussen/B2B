<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Enums;

enum CartStatus: string
{
    case Active = 'active';
    case Submitted = 'submitted';
}
