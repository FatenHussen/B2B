<?php

declare(strict_types=1);

namespace Modules\Notification\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Device push target. No channel column — a token belongs to an app user, not a tenant.
 *
 * @property int $id
 * @property string $recipient_kind
 * @property int $recipient_id
 * @property string $device_uuid
 * @property string $platform
 * @property string $token
 */
class PushToken extends Model
{
    protected $fillable = [
        'recipient_kind',
        'recipient_id',
        'device_uuid',
        'platform',
        'token',
    ];
}
