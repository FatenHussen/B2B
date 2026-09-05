<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

class StockReservation extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'sub_order_id',
        'warehouse_id',
        'product_id',
        'variant_id',
        'qty',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'released_at' => 'datetime',
        ];
    }
}
