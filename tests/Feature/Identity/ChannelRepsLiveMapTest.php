<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Delivery\Domain\Models\RepLocationPing;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\RepDutyState;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('lists on-duty reps with live pings and excludes off-duty', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $onDuty = AppSurface::rep($channel, $refs);
    $offDuty = AppSurface::rep($channel, $refs);

    RepDutyState::query()->updateOrCreate(
        ['rep_user_id' => $onDuty->id],
        ['on_duty' => true, 'tracking_enabled' => true],
    );
    RepDutyState::query()->updateOrCreate(
        ['rep_user_id' => $offDuty->id],
        ['on_duty' => false, 'tracking_enabled' => false],
    );

    RepLocationPing::query()->create([
        'rep_id' => $onDuty->id,
        'lat' => 33.51,
        'lng' => 36.29,
        'at' => now(),
        'accuracy' => 10,
    ]);
    RepLocationPing::query()->create([
        'rep_id' => $offDuty->id,
        'lat' => 33.40,
        'lng' => 36.20,
        'at' => now(),
        'accuracy' => 10,
    ]);

    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $response = $this->getJson('/api/v1/channel/reps/live');
    CatalogAssert::ok($response);

    $ids = collect($response->json('data'))->pluck('rep_id')->all();
    expect($ids)->toContain($onDuty->id)
        ->and($ids)->not->toContain($offDuty->id);

    $row = collect($response->json('data'))->firstWhere('rep_id', $onDuty->id);
    expect($row)->toMatchArray([
        'rep_id' => $onDuty->id,
        'name' => $onDuty->name,
        'lat' => 33.51,
        'lng' => 36.29,
        'on_duty' => true,
    ])
        ->and($row['zone_ids'])->toContain($refs['zone']->id)
        ->and($row['at'])->not->toBeNull();
});
