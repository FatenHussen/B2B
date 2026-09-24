<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $warehouse_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property string|null $lot_no
 * @property Carbon|null $expiry_date
 * @property int $qty
 */
class StockLot extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'warehouse_id',
        'product_id',
        'variant_id',
        'lot_no',
        'expiry_date',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'qty' => 'integer',
        ];
    }
}
