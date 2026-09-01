<?php

declare(strict_types=1);

namespace Modules\Integration\Otp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\OtpChannel;

final class SmsOtpChannel implements OtpChannel
{
    public function send(string $phone, string $code, string $purpose = 'login', string $via = 'sms'): void
    {
        $url = config('otp.sms.endpoint');

        if (! is_string($url) || $url === '') {
            Log::info("[SMS OTP] {$purpose} for {$phone}: {$code}");

            return;
        }

        Http::asForm()->post($url, [
            'to' => $phone,
            'message' => "رمز التحقق: {$code}",
        ])->throw();
    }
}
