<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryActivityType extends Model
{
    public $timestamps = false;

    protected $fillable = ['category_id', 'activity_type_id'];
}
