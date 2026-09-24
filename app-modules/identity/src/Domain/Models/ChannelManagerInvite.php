<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * A one-shot, 72-hour invitation for a channel's manager (PA-05 / EP-AD-064).
 *
 * Written by the platform back office about a channel, on a route that sets no
 * tenant; the future acceptance flow reads it by token before any tenant exists.
 * Relaxed channel scope for that reason (see `BelongsToChannel::channelScopeOptional()`).
 *
 * @property int $id
 * @property int $channel_id
 * @property string $token_hash
 * @property string $invite_via
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChannelManagerInvite extends Model
{
    use BelongsToChannel;

    protected string $channelColumn = 'channel_id';

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'channel_id',
        'token_hash',
        'invite_via',
        'expires_at',
        'consumed_at',
        'revoked_at',
        'created_by',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel_id' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }
}
