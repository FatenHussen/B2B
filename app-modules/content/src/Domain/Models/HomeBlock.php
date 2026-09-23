<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * Channel home block. Relaxed: `/app/content/home-blocks` has no tenant; the
 * query filters `whereIn(supply_channel_id, $channelIds)`.
 *
 * @property int $id
 * @property int $supply_channel_id
 * @property string $type
 * @property string|null $title
 * @property array<string, mixed>|null $payload
 * @property int $order
 * @property Carbon|null $active_from
 * @property Carbon|null $active_to
 * @property array<string, mixed>|null $targeting
 */
class HomeBlock extends Model
{
    use BelongsToChannel;

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'supply_channel_id',
        'type',
        'title',
        'payload',
        'order',
        'active_from',
        'active_to',
        'targeting',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'targeting' => 'array',
            'active_from' => 'datetime',
            'active_to' => 'datetime',
        ];
    }
}
