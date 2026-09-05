<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Ordering\Domain\Enums\CartLineSource;

class CartLine extends Model
{
    protected $fillable = [
        'section_id',
        'product_id',
        'variant_id',
        'qty',
        'source',
        'unit_price',
        'discount',
        'line_total',
        'applied_rule',
        'offer_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => CartLineSource::class,
            'applied_rule' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CartSection::class, 'section_id');
    }
}
