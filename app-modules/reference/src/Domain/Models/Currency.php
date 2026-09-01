<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Reference\Domain\Enums\RefStatus;

class Currency extends Model
{
    protected $fillable = ['code', 'name', 'symbol', 'is_base', 'status'];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'status' => RefStatus::class,
        ];
    }
}
