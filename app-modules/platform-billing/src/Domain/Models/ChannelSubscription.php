<?php

declare(strict_types=1);

namespace Modules\PlatformBilling\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * A channel's subscription to a plan (PA-06 / EP-AD-101, 102, 059A, 059B).
 *
 * Written by the platform back office about a channel, on routes that set no
 * tenant. Relaxed channel scope so that the first `/channel/*` route to read it is
 * isolated the day it lands (see `BelongsToChannel::channelScopeOptional()`).
 * `amount` is minor units (rule 7). `status` is guarded (rule 8).
 *
 * @property int $id
 * @property int $channel_id
 * @property int $plan_id
 * @property string $cycle
 * @property string $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $next_renewal_at
 * @property int $amount
 * @property int|null $scheduled_plan_id
 * @property Carbon|null $effective_from
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChannelSubscription extends Model
{
    use BelongsToChannel;

    protected string $channelColumn = 'channel_id';

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'channel_id', 'plan_id', 'cycle', 'starts_at', 'next_renewal_at',
        'amount', 'scheduled_plan_id', 'effective_from',
    ];

    /** @var list<string> */
    protected $guarded = ['status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel_id' => 'integer',
            'plan_id' => 'integer',
            'amount' => 'integer',
            'scheduled_plan_id' => 'integer',
            'starts_at' => 'datetime',
            'next_renewal_at' => 'datetime',
            'effective_from' => 'datetime',
        ];
    }
}
