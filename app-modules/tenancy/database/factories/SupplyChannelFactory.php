<?php

namespace Modules\Tenancy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * @extends Factory<SupplyChannel>
 */
class SupplyChannelFactory extends Factory
{
    protected $model = SupplyChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'legal_name' => $name,
            'tax_number' => (string) fake()->numerify('##########'),
            'phone' => '+9639'.fake()->numerify('########'),
            'email' => fake()->unique()->companyEmail(),
            'status' => 'active',
            'settings' => [],
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}
