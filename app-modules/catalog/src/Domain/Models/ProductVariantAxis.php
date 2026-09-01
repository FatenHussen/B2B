<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariantAxis extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'name', 'order'];

    public function values(): HasMany
    {
        return $this->hasMany(ProductVariantAxisValue::class, 'axis_id')->orderBy('order');
    }
}
