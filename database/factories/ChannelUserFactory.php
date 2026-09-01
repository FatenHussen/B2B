<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\ChannelUserChannel;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * @extends Factory<ChannelUser>
 */
class ChannelUserFactory extends Factory
{
    protected $model = ChannelUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+9639'.fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => UserStatus::Active,
        ];
    }

    public function forChannel(SupplyChannel|int $channel, bool $default = true): static
    {
        $channelId = $channel instanceof SupplyChannel ? $channel->id : $channel;

        return $this->afterCreating(function (ChannelUser $user) use ($channelId, $default): void {
            ChannelUserChannel::query()->create([
                'channel_user_id' => $user->id,
                'channel_id' => $channelId,
                'is_default' => $default,
            ]);
        });
    }
}
