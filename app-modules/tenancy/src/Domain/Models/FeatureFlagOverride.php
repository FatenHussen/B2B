<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlagOverride extends Model
{
    protected $fillable = [
        'feature_key',
        'channel_id',
        'enabled',
        'reason',
        'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'channel_id' => 'integer',
            'actor_id' => 'integer',
        ];
    }
}
