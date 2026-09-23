<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = [
        'app', 'platform', 'version', 'build', 'min_supported',
        'release_notes', 'rollout', 'store_url', 'force_update',
    ];

    protected function casts(): array
    {
        return [
            'force_update' => 'boolean',
            'rollout' => 'integer',
        ];
    }
}
