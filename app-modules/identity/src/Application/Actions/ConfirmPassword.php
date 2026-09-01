<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\PasswordConfirmation;
use Modules\Identity\Domain\Models\PlatformUser;

final class ConfirmPassword
{
    public function __invoke(PlatformUser $user, string $password): string
    {
        if (! Hash::check($password, $user->password)) {
            throw new DomainException(__('identity.invalid_password'), 'unauthenticated', 401);
        }

        $until = now()->addMinutes(15);

        PasswordConfirmation::query()->updateOrCreate(
            ['platform_user_id' => $user->id],
            ['confirmed_until' => $until],
        );

        return $until->timezone('Asia/Damascus')->toIso8601String();
    }
}
