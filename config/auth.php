<?php

declare(strict_types=1);

use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Models\WarehouseUser;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'platform'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'platform_users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'platform_users',
        ],
        'platform' => [
            'driver' => 'sanctum',
            'provider' => 'platform_users',
        ],
        'channel' => [
            'driver' => 'sanctum',
            'provider' => 'channel_users',
        ],
        'warehouse' => [
            'driver' => 'sanctum',
            'provider' => 'warehouse_users',
        ],
        'app' => [
            'driver' => 'sanctum',
            'provider' => 'app_users',
        ],
    ],

    'providers' => [
        'platform_users' => [
            'driver' => 'eloquent',
            'model' => PlatformUser::class,
        ],
        'channel_users' => [
            'driver' => 'eloquent',
            'model' => ChannelUser::class,
        ],
        'warehouse_users' => [
            'driver' => 'eloquent',
            'model' => WarehouseUser::class,
        ],
        'app_users' => [
            'driver' => 'eloquent',
            'model' => AppUser::class,
        ],
    ],

    'passwords' => [
        'platform_users' => [
            'provider' => 'platform_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 900),

];
