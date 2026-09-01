<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ProductActivityType extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'activity_type_id'];
}
