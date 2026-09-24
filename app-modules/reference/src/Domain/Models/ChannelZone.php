<?php

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Casts\MoneyCast;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Reference\Database\Factories\ChannelZoneFactory;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $zone_id
 * @property array<int, string>|null $delivery_days
 * @property list<array{day: string, start: string, end: string}>|null $delivery_windows
 * @property string $delivery_fee
 * @property string|null $min_order_value
 * @property Zone|null $zone
 */
class ChannelZone extends Model
{
    use BelongsToChannel;

    /** @use HasFactory<ChannelZoneFactory> */
    use HasFactory;

    protected $table = 'channel_zone';

    protected $fillable = [
        'zone_id',
        'delivery_days',
        'delivery_windows',
        'delivery_fee',
        'min_order_value',
    ];

    protected $attributes = [
        'delivery_fee' => 0,
    ];

    protected function casts(): array
    {
        return [
            'delivery_days' => 'array',
            'delivery_windows' => 'array',
            'delivery_fee' => MoneyCast::class,
            'min_order_value' => MoneyCast::class,
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    protected static function newFactory(): ChannelZoneFactory
    {
        return ChannelZoneFactory::new();
    }
}
