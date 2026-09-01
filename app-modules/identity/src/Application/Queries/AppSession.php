<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Core\Contracts\AccessCatalog;
use Modules\Identity\Domain\Models\AppUser;

final class AppSession
{
    public function __construct(private readonly AccessCatalog $access) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(AppUser $user): array
    {
        $user->load(['retailerProfile', 'repProfile']);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'user_type' => $user->kind?->value,
                'profile_completed' => $user->profileCompleted(),
            ],
            'permissions' => $this->access->permissionsFor($user),
            'feature_flags' => config('app.feature_flags', [
                'offline_orders' => false,
                'loyalty' => false,
            ]),
            'sync_cursor' => '',
            'server_time' => now()->timezone('Asia/Damascus')->toIso8601String(),
            'requires_legal_accept' => false,
            'legal' => [
                'privacy_version' => '2026-03',
                'terms_version' => '2026-01',
            ],
        ];
    }
}
