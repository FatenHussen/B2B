<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;

/**
 * @extends Factory<AppUser>
 */
class AppUserFactory extends Factory
{
    protected $model = AppUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+9639'.fake()->unique()->numerify('########'),
            'kind' => null,
            'status' => UserStatus::Pending,
        ];
    }

    public function retailer(): static
    {
        return $this->state(fn () => [
            'kind' => AppUserKind::Retailer,
            'status' => UserStatus::Active,
        ]);
    }

    public function rep(): static
    {
        return $this->state(fn () => [
            'kind' => AppUserKind::Rep,
            'status' => UserStatus::Active,
        ]);
    }
}
