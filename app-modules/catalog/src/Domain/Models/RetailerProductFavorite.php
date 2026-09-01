<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetailerProductFavorite extends Model
{
    protected $fillable = ['retailer_id', 'product_id'];
}
