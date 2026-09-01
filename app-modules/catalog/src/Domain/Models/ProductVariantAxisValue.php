<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantAxisValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['axis_id', 'value', 'order'];
}
