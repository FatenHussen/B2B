<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $category_id
 * @property int $activity_type_id
 */
class CategoryActivityType extends Model
{
    public $timestamps = false;

    protected $fillable = ['category_id', 'activity_type_id'];
}
