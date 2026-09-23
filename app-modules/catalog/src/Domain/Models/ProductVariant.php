<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'image_media_id',
        'combination',
        'status',
        'price_override',
    ];

    protected function casts(): array
    {
        return [
            'combination' => 'array',
            'price_override' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
