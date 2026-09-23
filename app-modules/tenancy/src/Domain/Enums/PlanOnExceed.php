<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Enums;

enum PlanOnExceed: string
{
    case Warn = 'warn';
    case Block = 'block';
    case ManualApproval = 'manual_approval';
}
