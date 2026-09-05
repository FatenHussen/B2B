<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RepDutyState extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rep_user_id',
        'on_duty',
        'tracking_enabled',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'on_duty' => 'boolean',
            'tracking_enabled' => 'boolean',
            'updated_at' => 'datetime',
        ];
    }
}
