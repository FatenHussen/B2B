<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PriceListItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['price_list_id', 'product_id', 'override_price'];

    protected function casts(): array
    {
        return ['override_price' => 'integer'];
    }
}
