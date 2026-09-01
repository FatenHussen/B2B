<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class BrandActivityType extends Model
{
    public $timestamps = false;

    protected $fillable = ['brand_id', 'activity_type_id'];
}
