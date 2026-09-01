<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

final class OtpException extends DomainException
{
    public static function invalid(): self
    {
        return new self(__('identity.otp_invalid'), 'otp_invalid', 401);
    }

    public static function expired(): self
    {
        return new self(__('identity.otp_expired'), 'otp_expired', 401);
    }

    public static function tooManyAttempts(): self
    {
        return new self(__('identity.otp_too_many_attempts'), 'otp_invalid', 401);
    }

    public static function cooldown(int $seconds): self
    {
        return new self(__('identity.otp_cooldown', ['seconds' => $seconds]), 'rate_limited', 429);
    }

    public static function rateLimited(): self
    {
        return new self(__('identity.otp_rate_limited'), 'rate_limited', 429);
    }

    public static function notFound(): self
    {
        return new self(__('identity.otp_not_found'), 'not_found', 404);
    }
}
