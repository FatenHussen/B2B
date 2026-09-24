<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Enums;

enum OtpPurpose: string
{
    case Login = 'login';
    case Register = 'register';
    case ChannelLogin = 'channel_login';
    /** Platform sensitive action (EP-AD-058 channel delete, etc.). */
    case PlatformChannelDelete = 'platform_channel_delete';
}
