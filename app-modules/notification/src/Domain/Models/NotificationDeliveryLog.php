<?php

declare(strict_types=1);

namespace Modules\Notification\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int|null $notification_id
 * @property int|null $recipient
 * @property string|null $template
 * @property string $status
 * @property string|null $failure_reason
 * @property Carbon|null $at
 */
class NotificationDeliveryLog extends Model
{
    use BelongsToChannel;

    protected $table = 'notification_delivery_log';

    public $timestamps = false;

    protected $fillable = [
        'supply_channel_id',
        'notification_id',
        'recipient',
        'template',
        'status',
        'failure_reason',
        'at',
    ];

    protected function casts(): array
    {
        return ['at' => 'datetime'];
    }
}
