<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Domain\Enums\ProductMediaRole;

class ProductMedia extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'media_id', 'role', 'order'];

    protected function casts(): array
    {
        return ['role' => ProductMediaRole::class];
    }
}
