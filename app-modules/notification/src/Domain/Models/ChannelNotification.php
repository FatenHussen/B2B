<?php

declare(strict_types=1);

namespace Modules\Notification\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $title
 * @property string $body
 * @property string|null $icon
 * @property string|null $image
 * @property array<string, mixed>|null $action
 * @property array<string, mixed> $targeting
 * @property list<string> $channels
 * @property string $status
 */
class ChannelNotification extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'title',
        'body',
        'icon',
        'image',
        'action',
        'targeting',
        'channels',
        'scheduled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'action' => 'array',
            'targeting' => 'array',
            'channels' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }
}
