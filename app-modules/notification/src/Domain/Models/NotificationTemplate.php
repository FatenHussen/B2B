<?php

declare(strict_types=1);

namespace Modules\Notification\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $event_key
 * @property string $title
 * @property string|null $body
 * @property bool $enabled
 * @property list<string> $channels
 */
class NotificationTemplate extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'event_key',
        'title',
        'body',
        'enabled',
        'channels',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'channels' => 'array',
        ];
    }
}
