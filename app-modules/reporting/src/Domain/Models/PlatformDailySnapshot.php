<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformDailySnapshot extends Model
{
    protected $fillable = ['snapshot_date', 'cards', 'charts', 'alerts'];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'cards' => 'array',
            'charts' => 'array',
            'alerts' => 'array',
        ];
    }
}
