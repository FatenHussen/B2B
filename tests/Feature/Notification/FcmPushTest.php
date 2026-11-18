<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Notification\Application\Support\DevicePushQueue;
use Modules\Notification\Application\Support\PushDispatcher;
use Modules\Notification\Domain\Models\PushToken;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function fcmOpenSslConfig(): ?string
{
    $fromEnv = getenv('OPENSSL_CONF');
    if (is_string($fromEnv) && $fromEnv !== '' && is_file($fromEnv)) {
        return $fromEnv;
    }

    foreach ([
        'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
        'C:\\xampp\\apache\\conf\\openssl.cnf',
        '/etc/ssl/openssl.cnf',
        '/etc/pki/tls/openssl.cnf',
        '/usr/lib/ssl/openssl.cnf',
        '/usr/local/etc/openssl/openssl.cnf',
    ] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * @return non-empty-string
 */
function fcmCredentialsFile(): string
{
    $options = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    $config = fcmOpenSslConfig();
    if ($config !== null) {
        $options['config'] = $config;
    }

    $key = openssl_pkey_new($options);
    $private = '';
    if ($key === false || openssl_pkey_export($key, $private, null, $options) !== true || $private === '') {
        throw new RuntimeException('openssl could not build a test key');
    }

    $path = tempnam(sys_get_temp_dir(), 'fcm');
    if ($path === false) {
        throw new RuntimeException('tempnam failed');
    }

    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'b2b-test',
        'private_key' => $private,
        'client_email' => 'push@b2b-test.iam.gserviceaccount.com',
    ], JSON_THROW_ON_ERROR));

    config(['firebase.credentials' => $path]);
    Cache::flush();

    return $path;
}

function fcmChannelManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('does not call Firebase when the service account is absent', function () {
    config(['firebase.credentials' => '']);
    Http::preventStrayRequests();
    Http::fake();

    app(DevicePushQueue::class)->send('retailer', 1, 'عنوان', 'نص', ['type' => 'order', 'target' => 1]);

    Http::assertNothingSent();
});

it('sends a high-priority banner to the registered device', function () {
    $path = fcmCredentialsFile();
    try {
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]);
            }

            return Http::response(['name' => 'projects/b2b-test/messages/1']);
        });

        $refs = AppSurface::refs();
        $retailer = AppSurface::retailer($refs);
        PushToken::query()->create([
            'recipient_kind' => 'retailer',
            'recipient_id' => $retailer->id,
            'device_uuid' => (string) Str::uuid(),
            'platform' => 'android',
            'token' => 'device-token-1',
        ]);

        app(DevicePushQueue::class)->send('retailer', (int) $retailer->id, 'تم تأكيد طلبك', 'الطلب قيد التجهيز', [
            'type' => 'order',
            'target' => 9,
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/messages:send')) {
                return false;
            }
            $message = $request->data()['message'] ?? null;
            if (! is_array($message)) {
                return false;
            }

            return ($message['token'] ?? null) === 'device-token-1'
                && ($message['notification']['title'] ?? null) === 'تم تأكيد طلبك'
                && ($message['notification']['body'] ?? null) === 'الطلب قيد التجهيز'
                && ($message['android']['priority'] ?? null) === 'HIGH'
                && ($message['data']['type'] ?? null) === 'order'
                && ($message['data']['target'] ?? null) === '9'
                && ($message['apns']['headers']['apns-priority'] ?? null) === '10';
        });
    } finally {
        @unlink($path);
    }
})->group('notifications');

it('drops a token Firebase says is no longer registered', function () {
    $path = fcmCredentialsFile();
    try {
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]);
            }

            return Http::response([
                'error' => [
                    'status' => 'NOT_FOUND',
                    'details' => [
                        ['errorCode' => 'UNREGISTERED'],
                    ],
                ],
            ], 404);
        });

        $refs = AppSurface::refs();
        $retailer = AppSurface::retailer($refs);
        PushToken::query()->create([
            'recipient_kind' => 'retailer',
            'recipient_id' => $retailer->id,
            'device_uuid' => (string) Str::uuid(),
            'platform' => 'android',
            'token' => 'dead-token',
        ]);

        $result = app(PushDispatcher::class)->toRecipient(
            'retailer',
            (int) $retailer->id,
            'عنوان',
            'نص',
            ['type' => 'order'],
        );

        expect($result)->toBe(['ok' => false, 'reason' => 'unregistered'])
            ->and(PushToken::query()->where('token', 'dead-token')->exists())->toBeFalse();
    } finally {
        @unlink($path);
    }
})->group('notifications');

it('marks a channel push sent when Firebase accepts the device token', function () {
    $path = fcmCredentialsFile();
    try {
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]);
            }

            return Http::response(['name' => 'projects/b2b-test/messages/1']);
        });

        $refs = AppSurface::refs();
        $channel = AppSurface::channel($refs);
        $retailer = AppSurface::retailer($refs);
        PushToken::query()->create([
            'recipient_kind' => 'retailer',
            'recipient_id' => $retailer->id,
            'device_uuid' => (string) Str::uuid(),
            'platform' => 'ios',
            'token' => 'campaign-token',
        ]);
        Sanctum::actingAs(fcmChannelManager($channel), ['*'], 'channel');

        $this->postJson('/api/v1/channel/notifications', [
            'title' => 'عرض',
            'body' => 'نص',
            'targeting' => ['type' => 'user', 'ids' => [$retailer->id]],
            'channels' => ['push', 'in_app'],
        ])->assertOk();

        $log = $this->getJson('/api/v1/channel/notifications/log');
        CatalogAssert::ok($log);
        expect($log->json('data.0.status'))->toBe('sent')
            ->and($log->json('data.0.failure_reason'))->toBeNull()
            ->and($log->json('data.0.recipient'))->toBe($retailer->id);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/messages:send')
            && ($request->data()['message']['token'] ?? null) === 'campaign-token');
    } finally {
        @unlink($path);
    }
})->group('notifications');

it('still banners the phone for an in-app campaign once Firebase is configured', function () {
    $path = fcmCredentialsFile();
    try {
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]);
            }

            return Http::response(['name' => 'projects/b2b-test/messages/1']);
        });

        $refs = AppSurface::refs();
        $channel = AppSurface::channel($refs);
        $retailer = AppSurface::retailer($refs);
        PushToken::query()->create([
            'recipient_kind' => 'retailer',
            'recipient_id' => $retailer->id,
            'device_uuid' => (string) Str::uuid(),
            'platform' => 'android',
            'token' => 'inbox-also-push',
        ]);
        Sanctum::actingAs(fcmChannelManager($channel), ['*'], 'channel');

        $this->postJson('/api/v1/channel/notifications', [
            'title' => 'داخل التطبيق',
            'body' => 'ويصل للموبايل',
            'targeting' => ['type' => 'user', 'ids' => [$retailer->id]],
            'channels' => ['in_app'],
        ])->assertOk();

        $log = $this->getJson('/api/v1/channel/notifications/log');
        expect($log->json('data.0.status'))->toBe('sent')
            ->and($log->json('data.0.failure_reason'))->toBeNull();
        Http::assertSent(fn ($request) => str_contains($request->url(), '/messages:send')
            && ($request->data()['message']['token'] ?? null) === 'inbox-also-push');
    } finally {
        @unlink($path);
    }
})->group('notifications');

it('records no_push_token when Firebase is configured but the retailer has no device', function () {
    $path = fcmCredentialsFile();
    try {
        Http::fake();

        $refs = AppSurface::refs();
        $channel = AppSurface::channel($refs);
        $retailer = AppSurface::retailer($refs);
        Sanctum::actingAs(fcmChannelManager($channel), ['*'], 'channel');

        $this->postJson('/api/v1/channel/notifications', [
            'title' => 'عرض',
            'body' => 'نص',
            'targeting' => ['type' => 'user', 'ids' => [$retailer->id]],
            'channels' => ['push', 'in_app'],
        ])->assertOk();

        $log = $this->getJson('/api/v1/channel/notifications/log');
        expect($log->json('data.0.status'))->toBe('partial')
            ->and($log->json('data.0.failure_reason'))->toBe('no_push_token');
        Http::assertNothingSent();
    } finally {
        @unlink($path);
    }
})->group('notifications');
