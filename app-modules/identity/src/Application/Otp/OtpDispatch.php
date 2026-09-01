<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Otp;

use Modules\Identity\Domain\Enums\OtpChannelUsed;

final class OtpDispatch
{
    public function __construct(
        public readonly string $otpId,
        public readonly OtpChannelUsed $channelUsed,
        public readonly int $expiresIn = 300,
        public readonly int $resendAfter = 60,
    ) {}
}
