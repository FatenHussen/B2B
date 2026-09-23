<?php

declare(strict_types=1);

namespace Modules\Loyalty\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * Per-channel wallet. Relaxed: `/app/*` has no tenant; owner_kind + owner_id isolate.
 *
 * @property int $id
 * @property int $supply_channel_id
 * @property string $owner_kind
 * @property int $owner_id
 * @property int $balance
 * @property string $tier
 */
class LoyaltyAccount extends Model
{
    use BelongsToChannel;

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'supply_channel_id',
        'owner_kind',
        'owner_id',
        'balance',
        'tier',
    ];
}
