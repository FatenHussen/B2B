<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRetailerGroup extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'group_id'];
}
