<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Enums\RepZoneRequestStatus;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RepProfileZone;
use Modules\Identity\Domain\Models\RepZoneRequest;

final class DecideRepZoneRequest
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array{decision: string, reason?: string|null}  $data
     * @return array{id: int, status: string}
     */
    public function __invoke(object $actor, int $requestId, array $data): array
    {
        $channelId = (int) Tenant::currentId();
        $request = RepZoneRequest::query()->whereKey($requestId)->first();
        if ($request === null) {
            throw DomainException::of(ErrorCode::NotFound, __('identity.not_found'));
        }

        $profile = RepProfile::query()
            ->whereKey($request->rep_id)
            ->where('channel_id', $channelId)
            ->first();
        if ($profile === null) {
            throw DomainException::of(ErrorCode::NotFound, __('identity.not_found'));
        }

        if ($request->status !== RepZoneRequestStatus::PendingApproval) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('identity.illegal_zone_request_transition'));
        }

        $approve = $data['decision'] === 'approve';

        return DB::transaction(function () use ($actor, $request, $profile, $approve, $data, $channelId): array {
            $status = $approve ? RepZoneRequestStatus::Approved : RepZoneRequestStatus::Rejected;
            $request->status = $status;
            $request->save();

            if ($approve) {
                $exists = RepProfileZone::query()
                    ->where('rep_profile_id', $profile->id)
                    ->where('zone_id', $request->zone_id)
                    ->exists();
                if (! $exists) {
                    RepProfileZone::query()->create([
                        'rep_profile_id' => $profile->id,
                        'zone_id' => (int) $request->zone_id,
                    ]);
                }
            }

            $this->audit->record('rep.zone.decide', $actor, 'rep_zone_request', (int) $request->id, [
                'after' => [
                    'decision' => $data['decision'],
                    'status' => $status->value,
                    'reason' => $data['reason'] ?? null,
                    'zone_id' => (int) $request->zone_id,
                ],
            ], $channelId);

            return ['id' => (int) $request->id, 'status' => $status->value];
        });
    }
}
