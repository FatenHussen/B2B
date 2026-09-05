<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Support;

final class OpaqueChannelRef
{
    public static function make(int $channelId): string
    {
        $hmac = hash_hmac('sha256', 'cart-channel:'.$channelId, (string) config('app.key'));

        return 'ch_'.substr($hmac, 0, 8);
    }
}
