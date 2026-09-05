<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'variant_id',
        'qty',
    ];
}
