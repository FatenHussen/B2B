<?php

declare(strict_types=1);

/**
 * AP-02 — EP-CM-060 … 063 app inbox + push token.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Notification\Domain\Models\AppNotification;
use Modules\Notification\Domain\Models\PushToken;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('lists inbox rows with unread_count and marks all read then clears the view', function () {
    $refs = AppSurface::refs();
    $rep = AppSurface::rep(AppSurface::channel($refs), $refs);
    AppNotification::query()->create([
        'channel_id' => null,
        'recipient_kind' => 'rep',
        'recipient_id' => $rep->id,
        'icon' => 'order',
        'title' => 'تم تأكيد طلبك',
        'body' => 'الطلب قيد التجهيز',
        'action' => ['type' => 'order', 'target' => 9001],
        'sent_at' => now(),
    ]);
    Sanctum::actingAs($rep, ['*'], 'app');

    $list = $this->getJson('/api/v1/app/notifications');
    CatalogAssert::ok($list);
    expect($list->json('meta.unread_count'))->toBe(1)
        ->and($list->json('data.0.title'))->toBe('تم تأكيد طلبك');

    CatalogAssert::ok($this->postJson('/api/v1/app/notifications/read-all'));
    expect($this->getJson('/api/v1/app/notifications')->json('meta.unread_count'))->toBe(0);

    CatalogAssert::ok($this->deleteJson('/api/v1/app/notifications'));
    expect($this->getJson('/api/v1/app/notifications')->json('data'))->toBe([]);
});

it('registers a push token bound to X-Device-Id and replaces older tokens for the same account', function () {
    $refs = AppSurface::refs();
    $rep = AppSurface::rep(AppSurface::channel($refs), $refs);
    Sanctum::actingAs($rep, ['*'], 'app');

    CatalogAssert::ok($this->postJson('/api/v1/app/devices/push-token', [
        'token' => 'fcm:first',
        'platform' => 'android',
    ], ['X-Device-Id' => 'device-a']));

    CatalogAssert::ok($this->postJson('/api/v1/app/devices/push-token', [
        'token' => 'fcm:second',
        'platform' => 'android',
    ], ['X-Device-Id' => 'device-b']));

    expect(PushToken::query()->where('recipient_id', $rep->id)->count())->toBe(1)
        ->and(PushToken::query()->where('device_uuid', 'device-b')->value('token'))->toBe('fcm:second');
});

it('rejects the inbox without a bearer', function () {
    CatalogAssert::error($this->getJson('/api/v1/app/notifications'), 401, 'unauthenticated');
});
