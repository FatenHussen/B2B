<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * The five whole-number limits a channel runs under (BE-T04), and the override window
 * BE-T12 adds.
 *
 * Channel-owned (rule 10): strict scope on `channel_id`. Written by the create action
 * with the channel id set explicitly, which needs no tenant; read from the back office
 * under `Tenant::as($channelId)`.
 */
class ChannelLimit extends Model
{
    use BelongsToChannel;

    protected $table = 'channel_limits';

    protected string $channelColumn = 'channel_id';

    protected $fillable = ['channel_id', 'users', 'warehouses', 'reps', 'skus', 'storage_mb', 'temporary_until', 'reason'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'users' => 'integer',
            'warehouses' => 'integer',
            'reps' => 'integer',
            'skus' => 'integer',
            'storage_mb' => 'integer',
            'temporary_until' => 'datetime',
        ];
    }
}
