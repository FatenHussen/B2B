<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSliderTag extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'slider_key'];
}
