<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;

/**
 * Minimal warehouse row for device-login (not fulfillment).
 *
 * @property int $id
 * @property int $channel_id
 * @property string $name
 * @property WarehouseStatus $status
 */
class Warehouse extends Model
{
    use BelongsToChannel;

    /**
     * This table names its channel `channel_id`, not `supply_channel_id`.
     * See CLAUDE.md rule 10 — both names count, and the trait reads this one.
     */
    protected string $channelColumn = 'channel_id';

    protected $fillable = [
        'channel_id',
        'name',
        'status',
        'lat',
        'lng',
    ];

    protected function casts(): array
    {
        return [
            'status' => WarehouseStatus::class,
            'lat' => 'float',
            'lng' => 'float',
        ];
    }
}
