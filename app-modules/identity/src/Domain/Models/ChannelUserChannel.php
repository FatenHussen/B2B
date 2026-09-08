<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Support\Concerns\BelongsToChannel;

final class ChannelUserChannel extends Model
{
    use BelongsToChannel;

    /**
     * This table names its channel `channel_id`, not `supply_channel_id`.
     *
     * Relaxed: `ResolveTenant` calls `ChannelUser::defaultChannelId()`, which reads
     * this table to discover the tenant. A scope that demanded the tenant in order to
     * read the table that supplies it could not terminate. Isolation here comes from
     * `channel_user_id` — a membership row is reachable only through its owner.
     *
     * Relaxed is not unscoped: with a tenant set the filter applies in full.
     */
    protected string $channelColumn = 'channel_id';

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'channel_user_id',
        'channel_id',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(ChannelUser::class, 'channel_user_id');
    }
}
