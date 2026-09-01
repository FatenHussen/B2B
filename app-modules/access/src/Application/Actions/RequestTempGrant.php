<?php

declare(strict_types=1);

namespace Modules\Access\Application\Actions;

use Modules\Access\Application\Services\SodChecker;
use Modules\Access\Domain\Enums\AccessChangeStatus;
use Modules\Access\Domain\Enums\AccessChangeType;
use Modules\Access\Domain\Enums\TempGrantStatus;
use Modules\Access\Domain\Models\AccessChangeRequest;
use Modules\Access\Domain\Models\TempGrant;
use Modules\Access\Infrastructure\GuardUserLocator;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Exceptions\DomainException;

final class RequestTempGrant
{
    public function __construct(
        private readonly SodChecker $sod,
        private readonly GuardUserLocator $users,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{user_id: int, permission: string, duration_minutes: int, reason: string}  $data
     * @return array{id: int, status: string, expires_request_at: string}
     */
    public function __invoke(object $actor, array $data): array
    {
        $this->sod->assertCatalogCodes([$data['permission']]);

        $user = $this->users->findForPermission($data['permission'], (int) $data['user_id']);
        if ($user === null) {
            throw new DomainException(__('access.user_not_found'), 'not_found', 404);
        }

        $expires = now()->addHours(24);

        $grant = TempGrant::query()->create([
            'grantee_type' => $this->users->morphAlias($user),
            'grantee_id' => (int) $user->getAuthIdentifier(),
            'permission' => $data['permission'],
            'status' => TempGrantStatus::PendingApproval,
            'duration_minutes' => (int) $data['duration_minutes'],
            'expires_request_at' => $expires,
            'requester_id' => (int) $actor->getAuthIdentifier(),
            'reason' => $data['reason'],
        ]);

        $request = AccessChangeRequest::query()->create([
            'type' => AccessChangeType::TempGrant,
            'status' => AccessChangeStatus::Pending,
            'permission' => 'ad.iam.grant_temp',
            'action' => 'temp_grant.request',
            'payload' => ['temp_grant_id' => $grant->id, 'permission' => $data['permission']],
            'requester_id' => (int) $actor->getAuthIdentifier(),
            'subject_id' => $grant->id,
        ]);

        $grant->request_id = $request->id;
        $grant->save();

        $this->audit->record(
            'temp_grant.request',
            $actor,
            'temp_grant',
            (int) $grant->id,
            ['after' => ['permission' => $data['permission'], 'user_id' => (int) $data['user_id']]],
        );

        return [
            'id' => (int) $grant->id,
            'status' => TempGrantStatus::PendingApproval->value,
            'expires_request_at' => $expires->timezone('Asia/Damascus')->toIso8601String(),
        ];
    }
}
