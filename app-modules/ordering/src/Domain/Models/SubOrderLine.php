<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubOrderLine extends Model
{
    protected $fillable = [
        'sub_order_id',
        'product_id',
        'variant_id',
        'qty',
        'unit_price',
        'discount',
        'line_total',
        'applied_rule',
        'offer_id',
    ];

    protected function casts(): array
    {
        return ['applied_rule' => 'array'];
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}
