<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Laravel\Sanctum\PersonalAccessToken;
use Modules\Identity\Domain\Models\PlatformSession;
use Modules\Identity\Domain\Models\PlatformUser;

final class RevokePlatformSession
{
    public function __invoke(PlatformUser $user, int $sessionId): void
    {
        $session = PlatformSession::query()
            ->where('platform_user_id', $user->id)
            ->whereKey($sessionId)
            ->firstOrFail();

        if ($session->token_id) {
            PersonalAccessToken::query()->whereKey($session->token_id)->delete();
        }

        $session->delete();
    }
}
