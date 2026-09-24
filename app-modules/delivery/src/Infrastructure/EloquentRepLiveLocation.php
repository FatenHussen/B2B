<?php

declare(strict_types=1);

namespace Modules\Delivery\Infrastructure;

use Modules\Core\Contracts\RepDutyLookup;
use Modules\Core\Contracts\RepLiveLocation;
use Modules\Delivery\Domain\Models\RepLocationPing;

final class EloquentRepLiveLocation implements RepLiveLocation
{
    public function __construct(private readonly RepDutyLookup $duty) {}

    public function latest(int $repUserId): ?array
    {
        $onDuty = $this->duty->isOnDuty($repUserId);
        $ping = RepLocationPing::query()
            ->where('rep_id', $repUserId)
            ->orderByDesc('id')
            ->first();

        if ($ping === null) {
            return [
                'lat' => null,
                'lng' => null,
                'at' => null,
                'on_duty' => $onDuty,
            ];
        }

        return [
            'lat' => (float) $ping->lat,
            'lng' => (float) $ping->lng,
            'at' => $ping->at?->timezone('Asia/Damascus')->toIso8601String(),
            'on_duty' => $onDuty,
        ];
    }
}
