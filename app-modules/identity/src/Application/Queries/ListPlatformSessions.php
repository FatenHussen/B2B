<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Illuminate\Support\Collection;
use Modules\Identity\Domain\Models\PlatformSession;
use Modules\Identity\Domain\Models\PlatformUser;

final class ListPlatformSessions
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(PlatformUser $user, ?string $currentTokenId): array
    {
        /** @var Collection<int, PlatformSession> $sessions */
        $sessions = PlatformSession::query()
            ->where('platform_user_id', $user->id)
            ->orderByDesc('last_active_at')
            ->get();

        return $sessions->map(fn (PlatformSession $session) => [
            'id' => (string) $session->id,
            'ip' => $session->ip,
            'agent' => $session->user_agent,
            'last_active_at' => $session->last_active_at?->timezone('Asia/Damascus')->toIso8601String(),
            'current' => $currentTokenId !== null && (string) $session->token_id === $currentTokenId,
        ])->all();
    }
}
