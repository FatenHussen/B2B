<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property Carbon $snapshot_date
 * @property array<string, mixed> $payload
 */
class DailySnapshot extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'snapshot_date',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'payload' => 'array',
        ];
    }
}
