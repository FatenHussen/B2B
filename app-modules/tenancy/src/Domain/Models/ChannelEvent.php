<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Tenancy\Domain\Enums\ChannelStatus;

/**
 * One status transition of one channel: from, to, who, why, when.
 *
 * Written only by `ChannelLifecycle`, never updated, never deleted — the audit trail
 * rule 8 asks for. `$timestamps` is off because `at` is the transition's own moment and
 * an `updated_at` on an immutable row would only ever lie.
 *
 * Platform-written *about* a channel (EP-AD-052 timeline). Relaxed — filters when a
 * tenant is set, tolerates its absence on the back office (no tenant after BF-07 closed
 * the `X-Channel-Id` switch). Was exempt while that switch existed; re-evaluated to
 * relaxed in the same commit. The writer sets `channel_id` explicitly.
 *
 * @property int $id
 * @property int $channel_id
 * @property ChannelStatus $from_status
 * @property ChannelStatus $to_status
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string $reason
 * @property Carbon $at
 */
class ChannelEvent extends Model
{
    use BelongsToChannel;

    protected $table = 'channel_events';

    public $timestamps = false;

    protected string $channelColumn = 'channel_id';

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'channel_id',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'reason',
        'at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ChannelStatus::class,
            'to_status' => ChannelStatus::class,
            'at' => 'datetime',
        ];
    }
}
