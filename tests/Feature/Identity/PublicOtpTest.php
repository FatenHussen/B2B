<?php

declare(strict_types=1);

use Modules\Core\Contracts\OtpChannel;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\OtpRequest;
use Modules\Identity\Domain\ValueObjects\PhoneNumber;
use Modules\Identity\Infrastructure\Otp\FakeOtpChannel;
use Tests\Support\CatalogAssert;

beforeEach(function () {
    $this->otp = app(OtpChannel::class);
    expect($this->otp)->toBeInstanceOf(FakeOtpChannel::class);
});

function requestOtp(string $phone = '+963912345678', string $purpose = 'register'): array
{
    $response = test()->postJson('/api/v1/public/auth/request-otp', [
        'phone' => $phone,
        'purpose' => $purpose,
        'client' => 'retailer-android',
    ]);

    CatalogAssert::ok($response, ['otp_id', 'channel_used', 'expires_in', 'resend_after']);

    /** @var FakeOtpChannel $fake */
    $fake = test()->otp;
    $normalized = PhoneNumber::normalize($phone);

    return [
        'otp_id' => $response->json('data.otp_id'),
        'code' => $fake->codeFor($normalized),
        'phone' => $normalized,
    ];
}

it('sends a code and returns the catalog otp payload', function () {
    $otp = requestOtp('0912345678');

    expect($otp['code'])->not->toBeNull()
        ->and($otp['otp_id'])->toStartWith('otp_')
        ->and(OtpRequest::query()->where('phone', '+963912345678')->count())->toBe(1);
});

it('rejects a non-syrian phone with 422', function () {
    CatalogAssert::error(
        $this->postJson('/api/v1/public/auth/request-otp', [
            'phone' => '12345',
            'purpose' => 'login',
        ]),
        422,
        'validation_failed',
    );
});

it('returns english validation messages even when Accept-Language is arabic', function () {
    $response = $this->withHeaders(['Accept-Language' => 'ar'])
        ->postJson('/api/v1/public/auth/request-otp', [
            'phone' => '12345',
            'purpose' => 'login',
        ]);

    CatalogAssert::error($response, 422, 'validation_failed');
    expect($response->json('error.message'))->toContain('Syrian')
        ->and($response->json('error.message'))->not->toContain('سوري');
});

it('returns a registration token when the phone has no completed profile', function () {
    $otp = requestOtp();

    $response = $this->postJson('/api/v1/public/auth/verify-otp', [
        'otp_id' => $otp['otp_id'],
        'code' => $otp['code'],
        'device_id' => 'device-1',
        'device_name' => 'Redmi',
        'platform' => 'android',
    ]);

    CatalogAssert::ok($response, ['token']);
    expect($response->json('data.is_new_user'))->toBeTrue()
        ->and($response->json('data.profile_completed'))->toBeFalse()
        ->and(AppUser::query()->where('phone', $otp['phone'])->count())->toBe(1);
});

it('logs in an existing retailer and issues a full token', function () {
    $user = AppUser::factory()->retailer()->create(['phone' => '+963912345678']);
    $otp = requestOtp('+963912345678', 'login');

    $response = $this->postJson('/api/v1/public/auth/verify-otp', [
        'otp_id' => $otp['otp_id'],
        'code' => $otp['code'],
        'device_id' => 'device-x',
        'device_name' => 'Pixel',
        'platform' => 'android',
    ]);

    CatalogAssert::ok($response);
    expect($response->json('data.is_new_user'))->toBeFalse()
        ->and($response->json('data.user_type'))->toBe('retailer')
        ->and($response->json('data.token'))->toBeString()
        ->and($user->refresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong code', function () {
    $otp = requestOtp();

    CatalogAssert::error(
        $this->postJson('/api/v1/public/auth/verify-otp', [
            'otp_id' => $otp['otp_id'],
            'code' => '000000',
            'device_id' => 'd1',
        ]),
        401,
        'otp_invalid',
    );
});

it('rejects an expired code', function () {
    $otp = requestOtp();

    $this->travel(6)->minutes();

    CatalogAssert::error(
        $this->postJson('/api/v1/public/auth/verify-otp', [
            'otp_id' => $otp['otp_id'],
            'code' => $otp['code'],
            'device_id' => 'd1',
        ]),
        401,
        'otp_expired',
    );
});

it('enforces a resend cooldown', function () {
    requestOtp();

    CatalogAssert::error(
        $this->postJson('/api/v1/public/auth/request-otp', [
            'phone' => '+963912345678',
            'purpose' => 'register',
        ]),
        429,
        'rate_limited',
    );
});

it('locks the otp after six failed attempts', function () {
    $otp = requestOtp();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/public/auth/verify-otp', [
            'otp_id' => $otp['otp_id'],
            'code' => '000000',
            'device_id' => 'd1',
        ])->assertStatus(401);
    }

    CatalogAssert::error(
        $this->postJson('/api/v1/public/auth/verify-otp', [
            'otp_id' => $otp['otp_id'],
            'code' => '000000',
            'device_id' => 'd1',
        ]),
        401,
        'otp_invalid',
    );
});

it('resends over sms after the cooldown window', function () {
    $otp = requestOtp();
    $this->travel(61)->seconds();

    $response = $this->postJson('/api/v1/public/auth/resend-otp', [
        'otp_id' => $otp['otp_id'],
        'prefer_channel' => 'sms',
    ]);

    CatalogAssert::ok($response, ['channel_used', 'resend_after']);
    expect($response->json('data.channel_used'))->toBe('sms');
});

it('issues a fixed all-zero code for rep clients outside production', function () {
    config(['otp.bypass' => false]);

    $response = $this->withHeaders(['X-Client' => 'rep-android'])->postJson('/api/v1/public/auth/request-otp', [
        'phone' => '+963912345678',
        'purpose' => 'register',
    ]);
    CatalogAssert::ok($response, ['otp_id']);

    /** @var FakeOtpChannel $fake */
    $fake = app(OtpChannel::class);
    expect($fake->codeFor('+963912345678'))->toBe('000000');

    $verify = $this->postJson('/api/v1/public/auth/verify-otp', [
        'otp_id' => $response->json('data.otp_id'),
        'code' => '000000',
        'device_id' => 'rep-device-1',
        'platform' => 'android',
    ]);
    CatalogAssert::ok($verify, ['token']);
});
