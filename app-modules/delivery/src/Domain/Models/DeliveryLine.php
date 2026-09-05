<?php

declare(strict_types=1);

namespace Modules\Delivery\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'delivery_id',
        'sub_order_line_id',
        'qty_expected',
        'qty_delivered',
        'action',
        'reason',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
