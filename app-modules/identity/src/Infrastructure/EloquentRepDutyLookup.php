<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\RepDutyLookup;
use Modules\Identity\Domain\Enums\ProfileStatus;
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

    public function onDutyCoveringZone(int $channelId, int $zoneId): array
    {
        $profileIds = RepProfile::query()
            ->where('channel_id', $channelId)
            ->where('status', ProfileStatus::Active)
            ->whereHas('zones', fn ($q) => $q->where('zone_id', $zoneId))
            ->pluck('app_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($profileIds === []) {
            return [];
        }

        $onDuty = RepDutyState::query()
            ->whereIn('rep_user_id', $profileIds)
            ->where('on_duty', true)
            ->pluck('rep_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($profileIds, $onDuty));
    }

    public function onDutyForChannel(int $channelId): array
    {
        $profileIds = RepProfile::query()
            ->where('channel_id', $channelId)
            ->where('status', ProfileStatus::Active)
            ->pluck('app_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($profileIds === []) {
            return [];
        }

        $onDuty = RepDutyState::query()
            ->whereIn('rep_user_id', $profileIds)
            ->where('on_duty', true)
            ->pluck('rep_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($profileIds, $onDuty));
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
