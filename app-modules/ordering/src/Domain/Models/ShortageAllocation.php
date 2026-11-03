<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $channel_id
 * @property int $warehouse_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property int $qty_available
 * @property string $reason
 * @property string $status
 */
class ShortageAllocation extends Model
{
    use BelongsToChannel;

    protected string $channelColumn = 'channel_id';

    protected $fillable = [
        'channel_id',
        'warehouse_id',
        'product_id',
        'variant_id',
        'qty_available',
        'reason',
        'status',
    ];

    /** @return HasMany<ShortageAllocationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ShortageAllocationLine::class);
    }
}
