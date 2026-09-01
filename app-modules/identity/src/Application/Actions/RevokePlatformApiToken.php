<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Modules\Identity\Domain\Models\PlatformUser;

final class RevokePlatformApiToken
{
    public function __invoke(PlatformUser $user, int $tokenId): void
    {
        $user->tokens()->whereKey($tokenId)->delete();
    }
}
