<?php

declare(strict_types=1);

namespace Modules\Integration\Otp;

use Illuminate\Support\Facades\Http;
use Modules\Core\Contracts\OtpChannel;
use RuntimeException;

final class WhatsAppOtpChannel implements OtpChannel
{
    public function send(string $phone, string $code, string $purpose = 'login', string $via = 'whatsapp'): void
    {
        $token = config('otp.whatsapp.token');
        $phoneId = config('otp.whatsapp.phone_id');

        if (! $token || ! $phoneId) {
            throw new RuntimeException(
                'WhatsApp OTP channel is not configured. Set WHATSAPP_TOKEN and WHATSAPP_PHONE_ID, or use OTP_CHANNEL=log.'
            );
        }

        $base = rtrim((string) config('otp.whatsapp.api_base'), '/');

        Http::withToken($token)
            ->post("{$base}/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($phone, '+'),
                'type' => 'text',
                'text' => ['body' => "رمز التحقق الخاص بك: {$code}"],
            ])
            ->throw();
    }
}
