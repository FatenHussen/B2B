<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $fillable = [
        'key',
        'description',
        'enabled_globally',
        'rollout_percent',
        'scopes',
        'planned_removal_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled_globally' => 'boolean',
            'rollout_percent' => 'integer',
            'scopes' => 'array',
            'planned_removal_at' => 'datetime',
        ];
    }
}
