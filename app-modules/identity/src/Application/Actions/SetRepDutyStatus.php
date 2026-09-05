<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Modules\Core\Contracts\RepDutyLookup;
use Modules\Identity\Domain\Models\AppUser;

final class SetRepDutyStatus
{
    public function __construct(private readonly RepDutyLookup $duty) {}

    /**
     * @return array{on_duty: bool, tracking_enabled: bool}
     */
    public function __invoke(AppUser $user, bool $onDuty): array
    {
        return $this->duty->setDuty((int) $user->id, $onDuty);
    }
}
