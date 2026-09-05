<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\RepDutyLookup;
use Modules\Identity\Domain\Models\RepDutyState;
use Modules\Identity\Domain\Models\RepProfile;

final class EloquentRepDutyLookup implements RepDutyLookup
{
    public function isOnDuty(int $repUserId): bool
    {
        return (bool) RepDutyState::query()->where('rep_user_id', $repUserId)->value('on_duty');
    }

    public function zoneIds(int $repUserId): array
    {
        $profile = RepProfile::query()->where('app_user_id', $repUserId)->first();

        return $profile?->zoneIds() ?? [];
    }

    public function trackingEnabled(int $repUserId): bool
    {
        return (bool) RepDutyState::query()->where('rep_user_id', $repUserId)->value('tracking_enabled');
    }

    public function setDuty(int $repUserId, bool $onDuty): array
    {
        $row = RepDutyState::query()->updateOrCreate(
            ['rep_user_id' => $repUserId],
            [
                'on_duty' => $onDuty,
                'tracking_enabled' => $onDuty,
                'updated_at' => now(),
            ],
        );

        return [
            'on_duty' => (bool) $row->on_duty,
            'tracking_enabled' => (bool) $row->tracking_enabled,
        ];
    }
}
