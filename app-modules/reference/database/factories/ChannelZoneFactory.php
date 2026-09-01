<?php

namespace Modules\Reference\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Zone;

/**
 * @extends Factory<ChannelZone>
 */
class ChannelZoneFactory extends Factory
{
    protected $model = ChannelZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supply_channel_id' => 1,
            'zone_id' => Zone::factory(),
            'delivery_days' => ['sun', 'mon', 'tue', 'wed', 'thu'],
            'delivery_fee' => '0.00',
            'min_order_value' => null,
        ];
    }
}
