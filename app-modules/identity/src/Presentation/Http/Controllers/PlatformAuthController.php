<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Contracts\VerifiesPlatformStepUpOtp;
use Modules\Core\Http\ApiController;
use Modules\Identity\Application\Actions\ChangePlatformPassword;
use Modules\Identity\Application\Actions\ConfirmPassword;
use Modules\Identity\Application\Actions\ConfirmTwoFactor;
use Modules\Identity\Application\Actions\EnableTwoFactor;
use Modules\Identity\Application\Actions\IssuePlatformApiToken;
use Modules\Identity\Application\Actions\LoginPlatform;
use Modules\Identity\Application\Actions\RevokePlatformApiToken;
use Modules\Identity\Application\Actions\RevokePlatformSession;
use Modules\Identity\Application\Actions\UpdatePlatformProfile;
use Modules\Identity\Application\Queries\ListPlatformApiTokens;
use Modules\Identity\Application\Queries\ListPlatformSessions;
use Modules\Identity\Application\Queries\PlatformMe;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Presentation\Http\Requests\ChangePasswordRequest;
use Modules\Identity\Presentation\Http\Requests\ConfirmPasswordRequest;
use Modules\Identity\Presentation\Http\Requests\ConfirmTwoFactorRequest;
use Modules\Identity\Presentation\Http\Requests\CreateApiTokenRequest;
use Modules\Identity\Presentation\Http\Requests\PlatformLoginRequest;
use Modules\Identity\Presentation\Http\Requests\RequestPlatformStepUpOtpRequest;
use Modules\Identity\Presentation\Http\Requests\UpdatePlatformProfileRequest;
use Modules\Identity\Presentation\Http\Requests\VerifyTwoFactorRequest;

final class PlatformAuthController extends ApiController
{
    public function login(PlatformLoginRequest $request, LoginPlatform $action): JsonResponse
    {
        return $this->ok($action(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->ip(),
            $request->userAgent(),
        ));
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request, LoginPlatform $action): JsonResponse
    {
        return $this->ok($action->verifyTwoFactor(
            $request->string('challenge_token')->toString(),
            $request->string('code')->toString(),
            $request->ip(),
            $request->userAgent(),
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->ok(['success' => true]);
    }

    public function me(Request $request, PlatformMe $query): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();

        return $this->ok($query($user));
    }

    public function confirmPassword(ConfirmPasswordRequest $request, ConfirmPassword $action): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();

        return $this->ok([
            'confirmed_until' => $action($user, $request->string('password')->toString()),
        ]);
    }

    /**
     * EP-AD-005A / BF-05 — challenge before sensitive platform writes (channel delete).
     */
    public function requestStepUpOtp(
        RequestPlatformStepUpOtpRequest $request,
        VerifiesPlatformStepUpOtp $stepUp,
    ): JsonResponse {
        /** @var PlatformUser $user */
        $user = $request->user();

        return $this->ok($stepUp->challenge(
            $user,
            $request->string('purpose')->toString(),
        ));
    }

    public function sessions(Request $request, ListPlatformSessions $query): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();
        $current = $request->user()?->currentAccessToken();

        return $this->ok($query($user, $current instanceof PersonalAccessToken ? (string) $current->id : null));
    }

    public function revokeSession(Request $request, int $id, RevokePlatformSession $action): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();
        $action($user, $id);

        return $this->ok(['success' => true]);
    }

    public function updateProfile(UpdatePlatformProfileRequest $request, UpdatePlatformProfile $action): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();
        $action($user, $request->validated());

        return $this->ok(['updated' => true]);
    }

    public function changePassword(ChangePasswordRequest $request, ChangePlatformPassword $action): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();
        $action(
            $user,
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return $this->ok(['updated' => true]);
    }

    public function enableTwoFactor(Request $request, EnableTwoFactor $action): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();

        return $this->ok($action($user));
    }

    public function confirmTwoFactor(ConfirmTwoFactorRequest $request, ConfirmTwoFactor $action): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();

        return $this->ok($action($user, $request->string('code')->toString()));
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();
        $codes = $user->two_factor_recovery_codes ?? [];

        return $this->ok([
            'codes_remaining' => is_array($codes) ? count($codes) : 0,
        ]);
    }

    public function apiTokens(Request $request, ListPlatformApiTokens $query): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();

        return $this->ok($query($user));
    }

    public function createApiToken(CreateApiTokenRequest $request, IssuePlatformApiToken $action): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();

        return $this->ok($action(
            $user,
            $request->string('name')->toString(),
            $request->string('password_confirmation')->toString(),
        ));
    }

    public function deleteApiToken(Request $request, int $id, RevokePlatformApiToken $action): JsonResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user();
        $action($user, $id);

        return $this->ok(['success' => true]);
    }
}
