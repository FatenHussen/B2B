<?php

namespace Modules\Reference\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reference\Domain\Models\Governorate;

/**
 * @extends Factory<Governorate>
 */
class GovernorateFactory extends Factory
{
    protected $model = Governorate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'name_ar' => $name,
            'name_en' => $name,
            'code' => fake()->unique()->lexify('???'),
        ];
    }
}
