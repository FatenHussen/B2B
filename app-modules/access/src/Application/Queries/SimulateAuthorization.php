<?php

declare(strict_types=1);

namespace Modules\Access\Application\Queries;

use Modules\Access\Application\Services\SodChecker;
use Modules\Access\Domain\Models\TempGrant;
use Modules\Access\Infrastructure\GuardUserLocator;

final class SimulateAuthorization
{
    public function __construct(
        private readonly GuardUserLocator $users,
        private readonly SodChecker $sod,
    ) {}

    /**
     * @param  array{user_type: string, user_id: int, permission: string, resource_type?: string|null, resource_id?: int|null}  $data
     * @return array{allowed: bool, decision_path: list<array{layer: string, result: string, reason: string}>}
     */
    public function __invoke(array $data): array
    {
        $userType = $data['user_type'];
        $permission = $data['permission'];
        $user = $this->users->find($userType, (int) $data['user_id']);

        $path = [];

        $guardOk = $user !== null;
        $path[] = [
            'layer' => 'guard',
            'result' => $guardOk ? 'pass' : 'fail',
            'reason' => $userType,
        ];

        $permOk = false;
        $permReason = 'none';
        if ($user !== null && method_exists($user, 'can')) {
            $permOk = (bool) $user->can($permission);
            if (method_exists($user, 'getRoleNames')) {
                $role = $user->getRoleNames()->first();
                $permReason = is_string($role) && $role !== '' ? 'role:'.$role : 'direct';
            }
        }
        $path[] = [
            'layer' => 'permission',
            'result' => $permOk ? 'pass' : 'fail',
            'reason' => $permReason,
        ];

        $path[] = [
            'layer' => 'tenant',
            'result' => 'pass',
            'reason' => 'same channel',
        ];

        $sodHit = $user !== null && $this->sod->wouldViolate($user, $permission);
        $path[] = [
            'layer' => 'sod',
            'result' => $sodHit ? 'fail' : 'pass',
            'reason' => $sodHit ? 'SOD-01' : 'clear',
        ];

        $tempOk = false;
        if ($user !== null) {
            $tempOk = TempGrant::query()
                ->where('grantee_type', $this->users->morphAlias($user))
                ->where('grantee_id', (int) $user->getAuthIdentifier())
                ->where('permission', $permission)
                ->get()
                ->contains(fn (TempGrant $g) => $g->isLive());
        }
        $path[] = [
            'layer' => 'temp-grant',
            'result' => $tempOk ? 'pass' : 'skip',
            'reason' => $tempOk ? 'active grant' : 'none',
        ];

        $allowed = $guardOk && ($permOk || $tempOk) && ! $sodHit;

        return [
            'allowed' => $allowed,
            'decision_path' => $path,
        ];
    }
}
