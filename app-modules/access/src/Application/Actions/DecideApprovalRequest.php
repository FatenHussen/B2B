<?php

declare(strict_types=1);

namespace Modules\Access\Application\Actions;

use Modules\Access\Domain\Enums\AccessChangeStatus;
use Modules\Access\Domain\Enums\AccessChangeType;
use Modules\Access\Domain\Models\AccessChangeRequest;
use Modules\Access\Domain\Models\AccessRole;
use Modules\Access\Domain\PermissionCatalog;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Exceptions\DomainException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class DecideApprovalRequest
{
    public function __construct(
        private readonly ApproveRole $approveRole,
        private readonly ApproveTempGrant $approveTempGrant,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{decision: string, reason: string}  $data
     * @return array{status: string, executed: bool}
     */
    public function __invoke(object $actor, int $id, array $data): array
    {
        $request = AccessChangeRequest::query()->find($id);
        if ($request === null) {
            throw new DomainException(__('access.not_found'), 'not_found', 404);
        }

        if ($request->status !== AccessChangeStatus::Pending) {
            throw new DomainException(__('access.request_not_pending'), 'conflict', 409);
        }

        $actorId = (int) $actor->getAuthIdentifier();
        if ((int) $request->requester_id === $actorId) {
            throw new DomainException(__('access.sod_approver_is_creator'), 'sod_violation', 403);
        }

        $approve = $data['decision'] === 'approve';

        if (! $approve) {
            $request->status = AccessChangeStatus::Rejected;
            $request->approver_id = $actorId;
            $request->reason = $data['reason'];
            $request->save();

            $this->audit->record('approval.reject', $actor, 'access_change_request', $request->id, [
                'reason' => $data['reason'],
            ]);

            return ['status' => AccessChangeStatus::Rejected->value, 'executed' => false];
        }

        $executed = false;
        if ($request->type === AccessChangeType::RoleCreate && $request->subject_id !== null) {
            $this->approveRole->__invoke($actor, (int) $request->subject_id, ['reason' => $data['reason']]);
            $executed = true;
        } elseif ($request->type === AccessChangeType::TempGrant && $request->subject_id !== null) {
            $this->approveTempGrant->__invoke($actor, (int) $request->subject_id, $data);
            $executed = true;
        } elseif ($request->type === AccessChangeType::RolePermissions && $request->subject_id !== null) {
            $this->applyRolePermissions($request);
            $executed = true;
        }

        $request->refresh();
        if ($request->status === AccessChangeStatus::Pending) {
            $request->status = AccessChangeStatus::Approved;
            $request->approver_id = $actorId;
            $request->reason = $data['reason'];
            $request->save();
        }

        return ['status' => AccessChangeStatus::Approved->value, 'executed' => $executed];
    }

    private function applyRolePermissions(AccessChangeRequest $request): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $role = AccessRole::query()->find((int) $request->subject_id);
        if ($role === null) {
            return;
        }

        $codes = $request->payload['permissions'] ?? [];
        $permissions = [];
        foreach ($codes as $code) {
            if (is_string($code) && PermissionCatalog::exists($code)) {
                $permissions[] = Permission::findOrCreate($code, $role->guard_name);
            }
        }
        $role->syncPermissions($permissions);
    }
}
