<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Reference\Domain\Enums\RefStatus;

class ActivityType extends Model
{
    protected $fillable = ['name', 'icon', 'description', 'order', 'status'];

    protected function casts(): array
    {
        return ['status' => RefStatus::class];
    }
}
