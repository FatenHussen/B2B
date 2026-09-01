<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface OtpChannel
{
    public function send(string $phone, string $code, string $purpose = 'login', string $via = 'whatsapp'): void;
}
