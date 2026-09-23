<?php

declare(strict_types=1);

namespace Modules\Loyalty\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $account_id
 * @property int $reward_id
 * @property string $redemption_no
 * @property Carbon|null $consumed_at
 */
class LoyaltyRedemption extends Model
{
    use BelongsToChannel;

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'supply_channel_id',
        'account_id',
        'reward_id',
        'redemption_no',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
        ];
    }
}
