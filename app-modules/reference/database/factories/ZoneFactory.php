<?php

namespace Modules\Reference\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reference\Domain\Enums\ZoneStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'governorate_id' => Governorate::factory(),
            'name' => fake()->unique()->streetName(),
            'polygon' => null,
            'status' => ZoneStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => ZoneStatus::Inactive->value]);
    }
}
