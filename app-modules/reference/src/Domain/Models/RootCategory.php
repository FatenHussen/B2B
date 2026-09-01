<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Reference\Domain\Enums\RefStatus;

class RootCategory extends Model
{
    protected $fillable = ['name', 'icon', 'image', 'order', 'status'];

    protected function casts(): array
    {
        return ['status' => RefStatus::class];
    }
}
