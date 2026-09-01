<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Enums;

enum ReviewItemDecision: string
{
    case Keep = 'keep';
    case Revoke = 'revoke';
}
