<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Support\InvalidFields;
use Modules\Identity\Domain\Models\PlatformUser;

final class ChangePlatformPassword
{
    public function __invoke(PlatformUser $user, string $current, string $password): void
    {
        if (! Hash::check($current, $user->password)) {
            InvalidFields::throw(['current_password' => 'identity.invalid_password']);
        }

        $user->forceFill(['password' => $password])->save();
    }
}
