<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Core\Contracts\AccessCatalog;
use Modules\Identity\Domain\Models\PlatformUser;

final class PlatformMe
{
    public function __construct(private readonly AccessCatalog $access) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(PlatformUser $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
            'roles' => $this->access->rolesFor($user),
            'permissions' => $this->access->permissionsFor($user),
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'last_login_at' => $user->last_login_at?->timezone('Asia/Damascus')->toIso8601String(),
        ];
    }
}
