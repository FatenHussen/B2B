<?php

declare(strict_types=1);

namespace Modules\Loyalty\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $account_id
 * @property string $type
 * @property int $points
 * @property string $reason
 * @property string|null $reference_type
 * @property int|null $reference_id
 */
class LoyaltyTransaction extends Model
{
    use BelongsToChannel;

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'supply_channel_id',
        'account_id',
        'type',
        'points',
        'reason',
        'reference_type',
        'reference_id',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'account_id');
    }
}
