<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Support\Totp;

final class EnableTwoFactor
{
    /**
     * @return array{secret: string, qr_svg: string}
     */
    public function __invoke(PlatformUser $user): array
    {
        $secret = Totp::secret();
        $user->forceFill(['pending_two_factor_secret' => $secret])->save();

        $issuer = rawurlencode((string) config('app.name'));
        $email = rawurlencode($user->email);

        return [
            'secret' => "otpauth://totp/{$issuer}:{$email}?secret={$secret}&issuer={$issuer}",
            'qr_svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>',
        ];
    }
}
