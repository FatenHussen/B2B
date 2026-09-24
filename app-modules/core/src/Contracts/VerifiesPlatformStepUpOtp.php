<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Step-up OTP for platform sensitive actions (channel delete, etc.).
 *
 * Identity implements: local/testing bypass, TOTP when 2FA is on, else phone OTP
 * via {@see challenge()} / {@see verify()}.
 */
interface VerifiesPlatformStepUpOtp
{
    public const PURPOSE_CHANNEL_DELETE = 'platform_channel_delete';

    /**
     * Issue a challenge for the authenticated platform actor.
     *
     * @return array{
     *     mode: 'bypass'|'totp'|'whatsapp'|'sms',
     *     otp_id: ?string,
     *     expires_in: int,
     *     resend_after: int,
     *     channel_used: ?string
     * }
     */
    public function challenge(object $actor, string $purpose): array;

    /**
     * Verify the code for the given purpose. Throws DomainException on failure.
     */
    public function verify(object $actor, string $purpose, string $code): void;
}
