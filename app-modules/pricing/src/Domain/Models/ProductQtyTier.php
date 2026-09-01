<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

class ProductQtyTier extends Model
{
    use BelongsToChannel;

    public $timestamps = false;

    protected $fillable = [
        'supply_channel_id',
        'product_id',
        'from_qty',
        'to_qty',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'from_qty' => 'integer',
            'to_qty' => 'integer',
            'price' => 'integer',
        ];
    }
}
