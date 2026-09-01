<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Support\InvalidFields;
use Modules\Identity\Domain\Enums\RepZoneRequestStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepZoneRequest;

final class RequestRepZone
{
    public function __construct(
        private readonly RepSellingContext $selling,
        private readonly ChannelDirectory $channels,
        private readonly ReferenceDirectory $refs,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{zone_id: int, note?: string|null}  $data
     * @return array{status: string}
     */
    public function __invoke(AppUser $user, array $data): array
    {
        $ctx = $this->selling->for($user);
        $channelId = $ctx['channel_ids'][0] ?? 0;
        $zoneId = (int) $data['zone_id'];

        if (! $this->refs->zoneIsActive($zoneId)) {
            InvalidFields::throw(['zone_id' => 'identity.zone_not_found']);
        }
        if ($channelId < 1 || ! $this->channels->coversZone($channelId, $zoneId)) {
            InvalidFields::throw(['zone_id' => 'identity.zone_outside_coverage']);
        }

        $request = RepZoneRequest::query()->create([
            'rep_id' => $ctx['rep_id'],
            'zone_id' => $zoneId,
            'note' => $data['note'] ?? null,
            'status' => RepZoneRequestStatus::PendingApproval,
        ]);

        $this->audit->record('rep.zone.request', $user, 'rep_zone_request', (int) $request->id, [
            'after' => ['zone_id' => $zoneId],
        ]);

        return ['status' => $request->status->value];
    }
}
