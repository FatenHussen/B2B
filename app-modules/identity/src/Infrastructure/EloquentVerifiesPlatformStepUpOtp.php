<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\VerifiesPlatformStepUpOtp;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Application\Services\OtpService;
use Modules\Identity\Domain\Enums\OtpPurpose;
use Modules\Identity\Domain\Exceptions\OtpException;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Support\Totp;

/**
 * BF-05 — real step-up for platform channel delete (and future sensitive actions).
 *
 * Prefer TOTP when the actor has 2FA; otherwise send/verify a phone OTP with
 * purpose {@see OtpPurpose::PlatformChannelDelete}. Local/testing honour
 * {@see OtpService::bypassed()}.
 */
final class EloquentVerifiesPlatformStepUpOtp implements VerifiesPlatformStepUpOtp
{
    public function __construct(private readonly OtpService $otp) {}

    public function challenge(object $actor, string $purpose): array
    {
        $user = $this->platformUser($actor);
        $this->assertKnownPurpose($purpose);

        if (OtpService::bypassed()) {
            return [
                'mode' => 'bypass',
                'otp_id' => null,
                'expires_in' => (int) config('otp.ttl', 300),
                'resend_after' => 0,
                'channel_used' => null,
            ];
        }

        if ($user->hasTwoFactorEnabled()) {
            return [
                'mode' => 'totp',
                'otp_id' => null,
                'expires_in' => 30,
                'resend_after' => 0,
                'channel_used' => 'totp',
            ];
        }

        $phone = trim((string) ($user->phone ?? ''));
        if ($phone === '') {
            throw DomainException::of(ErrorCode::ValidationFailed, __('identity.platform_otp_phone_required'));
        }

        $dispatch = $this->otp->request(
            phone: $phone,
            purpose: OtpPurpose::from($purpose),
            client: 'platform-web',
            ip: request()?->ip(),
            deviceId: request()?->header('X-Device-Id'),
        );

        return [
            'mode' => $dispatch->channelUsed->value,
            'otp_id' => $dispatch->otpId,
            'expires_in' => $dispatch->expiresIn,
            'resend_after' => $dispatch->resendAfter,
            'channel_used' => $dispatch->channelUsed->value,
        ];
    }

    public function verify(object $actor, string $purpose, string $code): void
    {
        $user = $this->platformUser($actor);
        $this->assertKnownPurpose($purpose);

        if (OtpService::bypassed()) {
            return;
        }

        if ($code === '' || ! preg_match('/^\d{4,8}$/', $code)) {
            throw DomainException::of(ErrorCode::ValidationFailed, __('tenancy.delete_otp_invalid'));
        }

        if ($user->hasTwoFactorEnabled()) {
            if (! Totp::verify((string) $user->two_factor_secret, $code)) {
                throw DomainException::of(ErrorCode::ValidationFailed, __('identity.invalid_2fa'));
            }

            return;
        }

        $phone = trim((string) ($user->phone ?? ''));
        if ($phone === '') {
            throw DomainException::of(ErrorCode::ValidationFailed, __('identity.platform_otp_phone_required'));
        }

        try {
            $this->otp->verifyLatest($phone, OtpPurpose::from($purpose), $code);
        } catch (OtpException) {
            throw DomainException::of(ErrorCode::ValidationFailed, __('tenancy.delete_otp_invalid'));
        }
    }

    private function platformUser(object $actor): PlatformUser
    {
        if (! $actor instanceof PlatformUser) {
            throw DomainException::of(ErrorCode::Unauthenticated, __('auth.failed'));
        }

        return $actor;
    }

    private function assertKnownPurpose(string $purpose): void
    {
        if ($purpose !== VerifiesPlatformStepUpOtp::PURPOSE_CHANNEL_DELETE) {
            throw DomainException::of(ErrorCode::ValidationFailed, __('identity.platform_otp_purpose_unknown'));
        }
    }
}
