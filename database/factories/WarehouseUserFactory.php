<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\WarehouseUser;

/**
 * @extends Factory<WarehouseUser>
 */
class WarehouseUserFactory extends Factory
{
    protected $model = WarehouseUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'status' => UserStatus::Active,
        ];
    }
}
