<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ProductUnitFactor extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'from_unit_id', 'to_unit_id', 'factor'];
}
