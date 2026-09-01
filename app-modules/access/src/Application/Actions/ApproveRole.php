<?php

declare(strict_types=1);

namespace Modules\Access\Application\Actions;

use Modules\Access\Application\Services\SodChecker;
use Modules\Access\Domain\Enums\AccessChangeStatus;
use Modules\Access\Domain\Enums\AccessChangeType;
use Modules\Access\Domain\Enums\RoleStatus;
use Modules\Access\Domain\Models\AccessChangeRequest;
use Modules\Access\Domain\Models\AccessRole;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Exceptions\DomainException;
use Spatie\Permission\PermissionRegistrar;

final class ApproveRole
{
    public function __construct(
        private readonly SodChecker $sod,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{reason?: string}  $data
     * @return array{status: string}
     */
    public function __invoke(object $actor, int $roleId, array $data = []): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $role = AccessRole::query()->find($roleId);
        if ($role === null) {
            throw new DomainException(__('access.not_found'), 'not_found', 404);
        }

        $actorId = (int) $actor->getAuthIdentifier();
        if ($role->created_by !== null && (int) $role->created_by === $actorId) {
            throw new DomainException(__('access.sod_approver_is_creator'), 'sod_violation', 403);
        }

        $this->sod->assertCompatible($role->permissions->pluck('name')->all());

        $before = $role->status->value;
        $role->status = RoleStatus::Active;
        $role->save();

        AccessChangeRequest::query()
            ->where('type', AccessChangeType::RoleCreate)
            ->where('subject_id', $role->id)
            ->where('status', AccessChangeStatus::Pending)
            ->update([
                'status' => AccessChangeStatus::Approved->value,
                'approver_id' => $actorId,
                'reason' => $data['reason'] ?? null,
            ]);

        $this->audit->record(
            'role.approve',
            $actor,
            'role',
            (int) $role->id,
            ['before' => ['status' => $before], 'after' => ['status' => RoleStatus::Active->value], 'reason' => $data['reason'] ?? null],
        );

        return ['status' => RoleStatus::Active->value];
    }
}
