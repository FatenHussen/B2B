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

    /**
     * Lifted for the same reason as `Cart::sections()` (BE-C12): a line is reached
     * through its cart's owner, and `whereHas('section', cart_id = …)` is the owner
     * check itself. The section is the line's own section or nothing.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CartSection::class, 'section_id')->withoutGlobalScope('channel');
    }
}
