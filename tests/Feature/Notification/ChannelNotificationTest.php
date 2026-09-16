<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function ntfManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('queues a channel notification and lists it on the log', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(ntfManager($channel), ['*'], 'channel');

    $send = $this->postJson('/api/v1/channel/notifications', [
        'title' => 'عرض جديد',
        'body' => 'اشترِ 10 واحصل على 1',
        'icon' => 'offer',
        'targeting' => ['type' => 'zone', 'ids' => [12]],
        'channels' => ['push', 'in_app'],
        'scheduled_at' => null,
    ]);
    CatalogAssert::ok($send);
    expect($send->json('data.status'))->toBe('queued');

    $log = $this->getJson('/api/v1/channel/notifications/log');
    CatalogAssert::ok($log);
    expect($log->json('data.0.status'))->toBe('queued');
});

it('upserts a notification template', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(ntfManager($channel), ['*'], 'channel');

    $put = $this->putJson('/api/v1/channel/notifications/templates', [
        'event_key' => 'order.confirmed',
        'title' => 'تم تأكيد طلبك',
        'body' => 'الطلب قيد التجهيز',
        'enabled' => true,
        'channels' => ['push', 'in_app'],
    ]);
    CatalogAssert::ok($put);

    $list = $this->getJson('/api/v1/channel/notifications/templates');
    CatalogAssert::ok($list);
    expect($list->json('data.0.event_key'))->toBe('order.confirmed');
});

it('hides another channel notification from the log', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    Sanctum::actingAs(ntfManager($foreign), ['*'], 'channel');
    $this->postJson('/api/v1/channel/notifications', [
        'title' => 'أجنبي',
        'body' => 'لا يظهر',
        'targeting' => ['type' => 'all', 'ids' => []],
        'channels' => ['in_app'],
    ])->assertOk();

    Sanctum::actingAs(ntfManager($own), ['*'], 'channel');
    $log = $this->getJson('/api/v1/channel/notifications/log');
    expect($log->json('data'))->toBe([]);
})->group('tenancy');
