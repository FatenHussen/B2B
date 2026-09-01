<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Enums;

enum OtpChannelUsed: string
{
    case Whatsapp = 'whatsapp';
    case Sms = 'sms';
}
