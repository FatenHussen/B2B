<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class StocktakeLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stocktake_id',
        'product_id',
        'variant_id',
        'location_id',
        'counted_qty',
    ];
}
