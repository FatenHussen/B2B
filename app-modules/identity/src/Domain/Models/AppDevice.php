<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $device_uuid
 * @property string|null $platform
 * @property string|null $name
 * @property string|null $push_token
 * @property Carbon|null $last_seen_at
 */
class AppDevice extends Model
{
    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'device_uuid',
        'platform',
        'name',
        'push_token',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
