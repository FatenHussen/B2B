<?php

declare(strict_types=1);

namespace Modules\Sync\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $device_uuid
 * @property int $app_user_id
 * @property string $op_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property Carbon|null $client_ts
 * @property string $status
 * @property array<string, mixed>|null $server_result
 */
class SyncOperation extends Model
{
    protected $fillable = [
        'device_uuid',
        'app_user_id',
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
