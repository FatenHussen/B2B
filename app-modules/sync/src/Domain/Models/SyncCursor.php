<?php

declare(strict_types=1);

namespace Modules\Sync\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $device_uuid
 * @property int $app_user_id
 * @property string|null $cursor
 * @property Carbon|null $pulled_at
 * @property Carbon|null $pushed_at
 */
class SyncCursor extends Model
{
    protected $fillable = [
        'device_uuid',
        'app_user_id',
        'cursor',
        'pulled_at',
        'pushed_at',
    ];

    protected function casts(): array
    {
        return [
            'pulled_at' => 'datetime',
            'pushed_at' => 'datetime',
        ];
    }
}
