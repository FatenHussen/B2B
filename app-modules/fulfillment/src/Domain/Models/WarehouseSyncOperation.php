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
 * @property string $op_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property Carbon|null $client_ts
 * @property string $status
 * @property array<string, mixed>|null $server_result
 */
class WarehouseSyncOperation extends Model
{
    use BelongsToChannel;

    protected string $channelColumn = 'channel_id';

    protected $fillable = [
        'device_uuid',
        'warehouse_user_id',
        'warehouse_id',
        'channel_id',
        'op_id',
        'type',
        'payload',
        'client_ts',
        'status',
        'server_result',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'server_result' => 'array',
            'client_ts' => 'datetime',
        ];
    }
}
