<?php

declare(strict_types=1);

use Modules\Core\Http\ApiResponse;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;

it('creates a channel user with phone-first identity', function () {
    $channel = SupplyChannel::factory()->create();

    $user = ChannelUser::factory()->forChannel($channel)->create([
        'phone' => '+963900123456',
    ]);

    expect($user->phone)->toBe('+963900123456')
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->defaultChannelId())->toBe($channel->id);
});

it('treats a supply channel as global (never channel-scoped)', function () {
    SupplyChannel::factory()->count(3)->create();

    expect(SupplyChannel::count())->toBe(3);
});

it('lets a platform admin exist with no channel', function () {
    $admin = PlatformUser::factory()->create();

    expect($admin->email)->not->toBeEmpty();
});

it('wraps data in the standard success envelope', function () {
    $response = ApiResponse::success(['id' => 1], ['page' => 1]);

    $payload = $response->getData(true);

    expect($response->getStatusCode())->toBe(200)
        ->and($payload['data'])->toBe(['id' => 1])
        ->and($payload['meta']['page'])->toBe(1)
        ->and($payload['meta'])->toHaveKey('server_time');
});

it('serves the health endpoint', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok');
});
