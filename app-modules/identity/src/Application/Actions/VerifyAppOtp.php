<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Modules\Identity\Application\Services\OtpService;
use Modules\Identity\Application\Services\TokenIssuer;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;

final class VerifyAppOtp
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly TokenIssuer $tokens,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        string $otpId,
        string $code,
        ?string $deviceId,
        ?string $deviceName,
        ?string $platform,
    ): array {
        $otp = $this->otp->verifyById($otpId, $code);

        $user = AppUser::query()->where('phone', $otp->phone)->first();

        if ($user === null) {
            $user = AppUser::query()->create([
                'name' => '',
                'phone' => $otp->phone,
                'kind' => null,
                'status' => UserStatus::Pending,
            ]);

            $token = $this->tokens->issue($user, ['registration'], $deviceId, $deviceName, $platform);

            return [
                'token' => $token,
                'is_new_user' => true,
                'user_type' => null,
                'profile_completed' => false,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                ],
            ];
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $user->load(['retailerProfile', 'repProfile']);

        $abilities = $user->profileCompleted() ? ['*'] : ['registration'];
        $token = $this->tokens->issue($user, $abilities, $deviceId, $deviceName, $platform);

        return [
            'token' => $token,
            'is_new_user' => false,
            'user_type' => $user->kind?->value,
            'profile_completed' => $user->profileCompleted(),
            'user' => $this->userPayload($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(AppUser $user): array
    {
        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
        ];

        if ($user->kind === AppUserKind::Retailer && $user->retailerProfile) {
            $profile = $user->retailerProfile;
            $payload['shop_name'] = $profile->shop_name;
            $payload['zone'] = ['id' => $profile->zone_id, 'name' => null];
            $payload['activity_type'] = ['id' => $profile->activity_type_id, 'name' => null];
        }

        return $payload;
    }
}
