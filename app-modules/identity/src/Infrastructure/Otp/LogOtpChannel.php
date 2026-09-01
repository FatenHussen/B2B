<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Otp;

use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\OtpChannel;

final class LogOtpChannel implements OtpChannel
{
    public function send(string $phone, string $code, string $purpose = 'login', string $via = 'whatsapp'): void
    {
        Log::info("[OTP] {$purpose} via {$via} for {$phone}: {$code}");
    }
}
