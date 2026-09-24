<?php

declare(strict_types=1);

namespace Modules\Loyalty\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Loyalty\Domain\Enums\LoyaltyRewardStatus;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $name
 * @property int $points_cost
 * @property int $stock
 * @property Carbon|null $expires_at
 * @property LoyaltyRewardStatus $status
 * @property string|null $stop_reason
 */
class LoyaltyReward extends Model
{
    use BelongsToChannel;

    protected $guarded = ['status'];

    protected $fillable = [
        'supply_channel_id',
        'name',
        'points_cost',
        'stock',
        'expires_at',
        'stop_reason',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'status' => LoyaltyRewardStatus::class,
        ];
    }
}
