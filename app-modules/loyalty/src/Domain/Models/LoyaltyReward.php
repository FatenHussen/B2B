<?php

declare(strict_types=1);

namespace Modules\Loyalty\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $name
 * @property int $points_cost
 * @property int $stock
 */
class LoyaltyReward extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'name',
        'points_cost',
        'stock',
        'expires_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'date'];
    }
}
