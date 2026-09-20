<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RetailerProfile;

final class ListRepZones
{
    public function __construct(
        private readonly RepDutyLookup $duty,
        private readonly ReferenceDirectory $refs,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(AppUser $user): array
    {
        $rows = [];
        foreach ($this->duty->zoneIds((int) $user->id) as $zoneId) {
            $rows[] = [
                'id' => $zoneId,
                'name' => $this->refs->zoneName($zoneId),
                'governorate_id' => $this->refs->zoneGovernorateId($zoneId),
                'shops_count' => RetailerProfile::query()
                    ->where('zone_id', $zoneId)
                    ->where('status', ProfileStatus::Active)
                    ->count(),
            ];
        }

        return $rows;
    }
}
