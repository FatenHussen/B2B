<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ProductZone extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'zone_id'];
}
