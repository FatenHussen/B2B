<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Reference\Domain\Enums\RefStatus;

class SaleUnit extends Model
{
    protected $fillable = ['name', 'abbr', 'default_factor', 'status'];

    protected function casts(): array
    {
        return [
            'default_factor' => 'integer',
            'status' => RefStatus::class,
        ];
    }
}
