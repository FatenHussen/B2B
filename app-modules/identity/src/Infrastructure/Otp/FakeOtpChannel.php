<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Otp;

use Modules\Core\Contracts\OtpChannel;

final class FakeOtpChannel implements OtpChannel
{
    /** @var array<string, string> */
    public array $sent = [];

    /** @var list<array{phone: string, code: string, purpose: string, via: string}> */
    public array $history = [];

    public string $lastVia = 'whatsapp';

    public function send(string $phone, string $code, string $purpose = 'login', string $via = 'whatsapp'): void
    {
        $this->sent[$phone] = $code;
        $this->lastVia = $via;
        $this->history[] = compact('phone', 'code', 'purpose', 'via');
    }

    public function codeFor(string $phone): ?string
    {
        return $this->sent[$phone] ?? null;
    }
}
