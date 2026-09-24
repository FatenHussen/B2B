<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function deliveryCalendarManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('returns a sunday-based week with zones on their delivery days', function () {
    $channel = SupplyChannel::factory()->create();
    $mezze = Zone::factory()->create(['name' => 'المزة']);
    $kafr = Zone::factory()->create(['name' => 'كفرسوسة']);

    Tenant::as($channel->id, function () use ($mezze, $kafr): void {
        ChannelZone::query()->create([
            'zone_id' => $mezze->id,
            'delivery_days' => ['sun', 'tue', 'thu'],
            'delivery_fee' => '0.00',
        ]);
        ChannelZone::query()->create([
            'zone_id' => $kafr->id,
            'delivery_days' => ['mon', 'wed'],
            'delivery_fee' => '0.00',
        ]);
    });

    Sanctum::actingAs(deliveryCalendarManager($channel), ['*'], 'channel');

    // 2026-09-21 is Monday; week_start must be Sunday 2026-09-20.
    $response = $this->getJson('/api/v1/channel/delivery-calendar?week=2026-09-21')
        ->assertOk()
        ->assertJsonPath('data.week_start', '2026-09-20');

    $days = $response->json('data.days');
    expect($days)->toHaveCount(7);
    expect(collect($days)->pluck('weekday')->all())->toBe(['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat']);
    expect(collect($days)->pluck('date')->all())->toBe([
        '2026-09-20',
        '2026-09-21',
        '2026-09-22',
        '2026-09-23',
        '2026-09-24',
        '2026-09-25',
        '2026-09-26',
    ]);

    expect($days[0]['zones'])->toBe([['zone_id' => $mezze->id, 'zone_name' => 'المزة']]);
    expect($days[1]['zones'])->toBe([['zone_id' => $kafr->id, 'zone_name' => 'كفرسوسة']]);
    expect($days[2]['zones'])->toBe([['zone_id' => $mezze->id, 'zone_name' => 'المزة']]);
    expect($days[3]['zones'])->toBe([['zone_id' => $kafr->id, 'zone_name' => 'كفرسوسة']]);
    expect($days[4]['zones'])->toBe([['zone_id' => $mezze->id, 'zone_name' => 'المزة']]);
    expect($days[5]['zones'])->toBe([]);
    expect($days[6]['zones'])->toBe([]);
})->group('reference');

it('defaults week to today in Asia/Damascus when week is omitted', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(deliveryCalendarManager($channel), ['*'], 'channel');

    $this->getJson('/api/v1/channel/delivery-calendar')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'week_start',
                'days' => [
                    ['date', 'weekday', 'zones'],
                ],
            ],
        ])
        ->assertJsonCount(7, 'data.days');
})->group('reference');
