<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\PlatformUser;

/**
 * @extends Factory<PlatformUser>
 */
class PlatformUserFactory extends Factory
{
    protected $model = PlatformUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+9639'.fake()->unique()->numerify('########'),
            'password' => 'password',
            'status' => UserStatus::Active,
        ];
    }
}
