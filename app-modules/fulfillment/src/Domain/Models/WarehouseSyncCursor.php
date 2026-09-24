<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property string $device_uuid
 * @property int $warehouse_user_id
 * @property int $warehouse_id
 * @property int $channel_id
 * @property Carbon|null $pushed_at
 */
class WarehouseSyncCursor extends Model
{
    use BelongsToChannel;

    protected string $channelColumn = 'channel_id';

    protected $fillable = [
        'device_uuid',
        'warehouse_user_id',
        'warehouse_id',
        'channel_id',
        'pushed_at',
    ];

    protected function casts(): array
    {
        return [
            'pushed_at' => 'datetime',
        ];
    }
}
