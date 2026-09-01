<?php

declare(strict_types=1);

namespace Modules\Access\Application\Actions;

use Modules\Access\Domain\Enums\AccessChangeStatus;
use Modules\Access\Domain\Enums\AccessChangeType;
use Modules\Access\Domain\Enums\TempGrantStatus;
use Modules\Access\Domain\Models\AccessChangeRequest;
use Modules\Access\Domain\Models\TempGrant;
use Modules\Access\Infrastructure\GuardUserLocator;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Exceptions\DomainException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class ApproveTempGrant
{
    public function __construct(
        private readonly GuardUserLocator $users,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{decision: string, reason: string}  $data
     * @return array{granted_until: string|null, status?: string}
     */
    public function __invoke(object $actor, int $grantId, array $data): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $grant = TempGrant::query()->find($grantId);
        if ($grant === null) {
            throw new DomainException(__('access.not_found'), 'not_found', 404);
        }

        $actorId = (int) $actor->getAuthIdentifier();
        if ((int) $grant->requester_id === $actorId) {
            throw new DomainException(__('access.sod_approver_is_creator'), 'sod_violation', 403);
        }

        $decision = $data['decision'];
        if ($decision === 'reject') {
            $grant->status = TempGrantStatus::Rejected;
            $grant->approver_id = $actorId;
            $grant->save();
            $this->closeRequest($grant, $actorId, AccessChangeStatus::Rejected, $data['reason']);

            return ['granted_until' => null, 'status' => TempGrantStatus::Rejected->value];
        }

        $until = now()->addMinutes($grant->duration_minutes);
        $grant->status = TempGrantStatus::Active;
        $grant->granted_until = $until;
        $grant->approver_id = $actorId;
        $grant->save();

        $user = $this->users->find(
            $this->guardFromMorph($grant->grantee_type),
            (int) $grant->grantee_id,
        );
        if ($user !== null && method_exists($user, 'givePermissionTo')) {
            $guard = method_exists($user, 'getDefaultGuardName') ? $user->getDefaultGuardName() : 'channel';
            $user->givePermissionTo(Permission::findOrCreate($grant->permission, $guard));
        }

        $this->closeRequest($grant, $actorId, AccessChangeStatus::Approved, $data['reason']);

        $this->audit->record(
            'temp_grant.approve',
            $actor,
            'temp_grant',
            (int) $grant->id,
            ['after' => ['granted_until' => $until->toIso8601String()], 'reason' => $data['reason']],
        );

        return ['granted_until' => $until->timezone('Asia/Damascus')->toIso8601String()];
    }

    private function closeRequest(TempGrant $grant, int $actorId, AccessChangeStatus $status, string $reason): void
    {
        AccessChangeRequest::query()
            ->where('type', AccessChangeType::TempGrant)
            ->where('subject_id', $grant->id)
            ->where('status', AccessChangeStatus::Pending)
            ->update([
                'status' => $status->value,
                'approver_id' => $actorId,
                'reason' => $reason,
            ]);
    }

    private function guardFromMorph(string $alias): string
    {
        return match ($alias) {
            'platform_user' => 'platform',
            'channel_user' => 'channel',
            'warehouse_user' => 'warehouse',
            'app_user' => 'app',
            default => 'channel',
        };
    }
}
