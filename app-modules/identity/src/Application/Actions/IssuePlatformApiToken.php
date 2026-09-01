<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\PlatformUser;

final class IssuePlatformApiToken
{
    /**
     * @return array{id: int|string, token: string}
     */
    public function __invoke(PlatformUser $user, string $name, string $password): array
    {
        if (! Hash::check($password, $user->password)) {
            throw new DomainException(__('identity.requires_password_confirm'), 'requires_password_confirm', 403);
        }

        $new = $user->createToken($name, ['*']);

        return [
            'id' => $new->accessToken->id,
            'token' => $new->plainTextToken,
        ];
    }
}
