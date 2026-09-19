<?php

declare(strict_types=1);

/**
 * `otp.bypass` — the development switch that turns OTP verification off.
 *
 * The flow, the endpoints and the payloads do not change: request-otp still hands
 * out an otp_id and verify-otp still needs it. What changes is that the code is
 * never sent, checked or even validated — any value or none — and the cooldown and
 * rate limits are off. The last test pins the only thing that matters about the
 * switch — production ignores it.
 */

use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\OtpChannel;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Infrastructure\Otp\FakeOtpChannel;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(function () {
    config(['otp.bypass' => true]);
    $this->otp = app(OtpChannel::class);
    expect($this->otp)->toBeInstanceOf(FakeOtpChannel::class);
});

function requestBypassedOtp(string $phone = '+963912345678', string $purpose = 'login'): string
{
    $response = test()->postJson('/api/v1/public/auth/request-otp', [
        'phone' => $phone,
        'purpose' => $purpose,
    ]);

    CatalogAssert::ok($response, ['otp_id', 'channel_used', 'expires_in', 'resend_after']);

    return $response->json('data.otp_id');
}

it('verifies any six-character code and never sends one', function () {
    AppUser::factory()->retailer()->create(['phone' => '+963912345678']);
    $otpId = requestBypassedOtp();

    /** @var FakeOtpChannel $fake */
    $fake = $this->otp;
    expect($fake->history)->toBe([]);

    $response = $this->postJson('/api/v1/public/auth/verify-otp', [
        'otp_id' => $otpId,
        'code' => '000000',
        'device_id' => 'device-1',
    ]);

    CatalogAssert::ok($response, ['token']);
    expect($response->json('data.user_type'))->toBe('retailer');
});

it('accepts a code of any shape, or no code at all', function () {
    AppUser::factory()->retailer()->create(['phone' => '+963912345678']);

    foreach (['0000', 'abc', 1234, null] as $code) {
        $payload = ['otp_id' => requestBypassedOtp(), 'device_id' => 'device-1'];
        if ($code !== null) {
            $payload['code'] = $code;
        }

        CatalogAssert::ok($this->postJson('/api/v1/public/auth/verify-otp', $payload), ['token']);
    }
});

it('still requires an otp_id that exists and has not been used', function () {
    $otpId = requestBypassedOtp();

    $this->postJson('/api/v1/public/auth/verify-otp', [
        'otp_id' => $otpId,
        'code' => '000000',
        'device_id' => 'device-1',
    ])->assertOk();

    CatalogAssert::error(
        $this->postJson('/api/v1/public/auth/verify-otp', [
            'otp_id' => $otpId,
            'code' => '000000',
            'device_id' => 'device-1',
        ]),
        401,
        'otp_expired',
    );

    CatalogAssert::error(
        $this->postJson('/api/v1/public/auth/verify-otp', [
            'otp_id' => 'otp_nope',
            'code' => '000000',
            'device_id' => 'device-1',
        ]),
        401,
        'otp_invalid',
    );
});

it('skips the resend cooldown and the per-phone rate limit', function () {
    // Four back-to-back requests: the fourth would be the 3-per-phone-per-hour limit
    // and every one after the first would be inside the 60 s cooldown.
    foreach (range(1, 4) as $_) {
        requestBypassedOtp();
    }

    $otpId = requestBypassedOtp();

    $response = $this->postJson('/api/v1/public/auth/resend-otp', [
        'otp_id' => $otpId,
        'prefer_channel' => 'whatsapp',
    ]);

    CatalogAssert::ok($response, ['channel_used', 'resend_after']);
});

it('applies to channel login as well', function () {
    $this->seed(RolesPermissionsSeeder::class);
    $channel = SupplyChannel::factory()->create();
    $member = ChannelUser::factory()->forChannel($channel)->create(['phone' => '+963944000000']);
    $member->assignRole('channel_manager');

    $requested = $this->postJson('/api/v1/channel/auth/request-otp', ['phone' => '+963944000000']);
    CatalogAssert::ok($requested, ['otp_id']);

    $verified = $this->postJson('/api/v1/channel/auth/verify-otp', [
        'otp_id' => $requested->json('data.otp_id'),
        'code' => '1',
    ]);

    CatalogAssert::ok($verified, ['token', 'channels']);
    expect($verified->json('data.channels.0.id'))->toBe($channel->id);
});

it('is ignored in production no matter what the env says', function () {
    $this->app['env'] = 'production';

    $otpId = requestBypassedOtp();

    /** @var FakeOtpChannel $fake */
    $fake = $this->otp;
    $sent = $fake->codeFor('+963912345678');
    expect($sent)->not->toBeNull();

    // The shape is validated again too: production wants exactly six characters.
    CatalogAssert::error(
        $this->postJson('/api/v1/public/auth/verify-otp', [
            'otp_id' => $otpId,
            'code' => '1',
            'device_id' => 'device-1',
        ]),
        422,
        'validation_failed',
    );

    CatalogAssert::error(
        $this->postJson('/api/v1/public/auth/verify-otp', [
            'otp_id' => $otpId,
            'code' => $sent === '000000' ? '111111' : '000000',
            'device_id' => 'device-1',
        ]),
        401,
        'otp_invalid',
    );
});
