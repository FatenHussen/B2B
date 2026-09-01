<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Identity\Domain\Models\PlatformUser;

final class ListPlatformApiTokens
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(PlatformUser $user): array
    {
        return $user->tokens()->orderByDesc('id')->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at?->timezone('Asia/Damascus')->toIso8601String(),
            'created_at' => $token->created_at?->timezone('Asia/Damascus')->toIso8601String(),
        ])->all();
    }
}
