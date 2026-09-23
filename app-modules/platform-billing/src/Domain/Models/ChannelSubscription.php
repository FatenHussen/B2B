<?php

declare(strict_types=1);

namespace Modules\PlatformBilling\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelSubscription extends Model
{
    protected $fillable = [
        'channel_id', 'plan_id', 'cycle', 'starts_at', 'next_renewal_at',
        'amount', 'scheduled_plan_id', 'effective_from',
    ];

    /** @var list<string> */
    protected $guarded = ['status'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'starts_at' => 'datetime',
            'next_renewal_at' => 'datetime',
            'effective_from' => 'datetime',
        ];
    }
}
