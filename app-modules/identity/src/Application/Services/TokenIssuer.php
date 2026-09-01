<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Domain\Models\AppDevice;

final class TokenIssuer
{
    /**
     * @param  list<string>  $abilities
     */
    public function issue(
        Authenticatable $user,
        array $abilities = ['*'],
        ?string $deviceUuid = null,
        ?string $deviceName = null,
        ?string $platform = null,
        ?string $pushToken = null,
    ): string {
        $tokenName = $deviceUuid ?: 'default';

        if ($deviceUuid !== null) {
            AppDevice::query()->updateOrCreate(
                [
                    'tokenable_type' => $user::class,
                    'tokenable_id' => $user->getAuthIdentifier(),
                    'device_uuid' => $deviceUuid,
                ],
                [
                    'platform' => $platform,
                    'name' => $deviceName,
                    'push_token' => $pushToken,
                    'last_seen_at' => now(),
                ],
            );
        }

        if (in_array(HasApiTokens::class, class_uses_recursive($user), true)) {
            /** @var Authenticatable&HasApiTokens $user */
            $user->tokens()->where('name', $tokenName)->delete();

            return $user->createToken($tokenName, $abilities)->plainTextToken;
        }

        throw new \RuntimeException('User model cannot issue API tokens.');
    }
}
