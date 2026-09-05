<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'goods_receipt_id',
        'product_id',
        'variant_id',
        'qty_expected',
        'qty_received',
        'lot_no',
        'expiry_date',
        'location_id',
    ];
}
