<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Support\Totp;

final class ConfirmTwoFactor
{
    /**
     * @return array{enabled: true, recovery_codes: list<string>}
     */
    public function __invoke(PlatformUser $user, string $code): array
    {
        $pending = (string) $user->pending_two_factor_secret;

        if ($pending === '' || ! Totp::verify($pending, $code)) {
            throw new DomainException(__('identity.invalid_2fa'), 'otp_invalid', 401);
        }

        $codes = [];
        $stored = [];
        for ($i = 0; $i < 8; $i++) {
            $plain = bin2hex(random_bytes(4));
            $codes[] = $plain;
            $stored[] = Hash::make($plain);
        }

        $user->forceFill([
            'two_factor_secret' => $pending,
            'pending_two_factor_secret' => null,
            'two_factor_recovery_codes' => $stored,
        ])->save();

        return [
            'enabled' => true,
            'recovery_codes' => $codes,
        ];
    }
}
