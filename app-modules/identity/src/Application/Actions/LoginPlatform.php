<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Contracts\AccessCatalog;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Application\Services\TokenIssuer;
use Modules\Identity\Domain\Models\OtpChallenge;
use Modules\Identity\Domain\Models\PlatformSession;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Support\Totp;

final class LoginPlatform
{
    public function __construct(
        private readonly TokenIssuer $tokens,
        private readonly AccessCatalog $access,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $email, string $password, ?string $ip = null, ?string $userAgent = null): array
    {
        $user = PlatformUser::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new DomainException(__('auth.failed'), 'unauthenticated', 401);
        }

        if ($user->hasTwoFactorEnabled()) {
            $challenge = OtpChallenge::query()->create([
                'public_id' => 'cht_'.Str::lower(Str::random(8)),
                'platform_user_id' => $user->id,
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
            ]);

            return [
                'requires_2fa' => true,
                'challenge_token' => $challenge->public_id,
            ];
        }

        return $this->issueSession($user, $ip, $userAgent);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyTwoFactor(string $challengeToken, string $code, ?string $ip = null, ?string $userAgent = null): array
    {
        $challenge = OtpChallenge::query()->where('public_id', $challengeToken)->first();

        if ($challenge === null || $challenge->isExpired()) {
            throw new DomainException(__('identity.challenge_invalid'), 'otp_invalid', 401);
        }

        if ($challenge->attempts >= 5) {
            throw new DomainException(__('identity.too_many_2fa'), 'otp_invalid', 401);
        }

        $challenge->increment('attempts');
        $user = $challenge->user;

        $valid = Totp::verify((string) $user->two_factor_secret, $code);
        $recoveryCodes = is_array($user->two_factor_recovery_codes) ? $user->two_factor_recovery_codes : [];
        $matchedRecoveryIndex = null;

        foreach ($recoveryCodes as $index => $stored) {
            if (is_string($stored) && Hash::check($code, $stored)) {
                $matchedRecoveryIndex = $index;
                break;
            }
        }

        if (! $valid && $matchedRecoveryIndex === null) {
            throw new DomainException(__('identity.invalid_2fa'), 'otp_invalid', 401);
        }

        if ($matchedRecoveryIndex !== null) {
            // A used recovery code cannot be reused (BE-I15). Drop it before issuing
            // the session so a replay of the same code fails.
            unset($recoveryCodes[$matchedRecoveryIndex]);
            $user->forceFill([
                'two_factor_recovery_codes' => array_values($recoveryCodes),
            ])->save();
        }

        $challenge->delete();

        return $this->issueSession($user->fresh(), $ip, $userAgent);
    }

    /**
     * @return array<string, mixed>
     */
    public function issueSession(PlatformUser $user, ?string $ip, ?string $userAgent): array
    {
        $user->forceFill(['last_login_at' => now()])->save();
        $plain = $this->tokens->issue($user, ['*']);
        $tokenId = explode('|', $plain, 2)[0] ?? null;

        PlatformSession::query()->create([
            'platform_user_id' => $user->id,
            'token_id' => $tokenId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'last_active_at' => now(),
        ]);

        return [
            'token' => $plain,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $this->access->rolesFor($user),
                'permissions' => $this->access->permissionsFor($user),
            ],
            'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 60 * 24))->timezone('Asia/Damascus')->toIso8601String(),
        ];
    }
}
