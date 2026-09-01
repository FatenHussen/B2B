<?php

declare(strict_types=1);

namespace Modules\Integration\Otp;

use Modules\Core\Contracts\OtpChannel;
use Throwable;

final class CompositeOtpChannel implements OtpChannel
{
    public function __construct(
        private readonly WhatsAppOtpChannel $whatsapp,
        private readonly SmsOtpChannel $sms,
    ) {}

    public function send(string $phone, string $code, string $purpose = 'login', string $via = 'whatsapp'): void
    {
        if ($via === 'sms') {
            $this->sms->send($phone, $code, $purpose, 'sms');

            return;
        }

        try {
            $this->whatsapp->send($phone, $code, $purpose, 'whatsapp');
        } catch (Throwable) {
            $this->sms->send($phone, $code, $purpose, 'sms');
        }
    }
}
