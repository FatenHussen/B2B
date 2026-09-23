<?php

declare(strict_types=1);

namespace Modules\Notification\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * App inbox row. `/app/*` sets no tenant, so the scope is relaxed; every read
 * filters by recipient_kind + recipient_id (the owner), never by listing the table.
 *
 * @property int $id
 * @property int|null $channel_id
 * @property string $recipient_kind
 * @property int $recipient_id
 * @property string|null $icon
 * @property string $title
 * @property string $body
 * @property array<string, mixed>|null $action
 * @property Carbon|null $read_at
 * @property Carbon|null $dismissed_at
 * @property Carbon|null $sent_at
 */
class AppNotification extends Model
{
    use BelongsToChannel;

    protected string $channelColumn = 'channel_id';

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'channel_id',
        'recipient_kind',
        'recipient_id',
        'icon',
        'title',
        'body',
        'action',
        'read_at',
        'dismissed_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => 'array',
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
