<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Enums;

enum ReviewStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
