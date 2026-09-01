<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class BrandSlider extends Model
{
    protected $fillable = ['brand_id', 'name', 'source', 'source_id', 'count', 'order'];
}
