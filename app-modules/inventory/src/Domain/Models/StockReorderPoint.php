<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

class StockReorderPoint extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'warehouse_id',
        'product_id',
        'variant_id',
        'point',
    ];
}
