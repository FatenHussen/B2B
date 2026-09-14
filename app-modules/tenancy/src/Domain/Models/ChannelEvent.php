<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Tenancy\Domain\Enums\ChannelStatus;

/**
 * One status transition of one channel: from, to, who, why, when.
 *
 * Written only by `ChannelLifecycle`, never updated, never deleted — the audit trail
 * rule 8 asks for. `$timestamps` is off because `at` is the transition's own moment and
 * an `updated_at` on an immutable row would only ever lie.
 *
 * Carries no channel scope, and is listed as such in `ChannelScopeTest`: this is the
 * platform's record *about* a channel — written from the back office, read on the
 * back-office timeline (EP-AD-052) — not data the channel owns. `channel_id` here is a
 * foreign key to the tenant table, the same way `AuditLog.channel_id` is, and scoping it
 * by tenant would hide the trail from the only audience that reads it.
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
    protected $table = 'channel_events';

    public $timestamps = false;

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
