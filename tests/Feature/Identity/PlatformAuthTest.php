<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Support\Totp;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function totpNow(string $secret): string
{
    return Totp::at($secret, (int) floor(time() / 30));
}

it('logs in a platform user without 2fa', function () {
    $user = PlatformUser::factory()->create([
        'email' => 'admin@platform.sy',
        'password' => 'password',
    ]);
    $user->assignRole('platform_admin');

    $response = $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'admin@platform.sy',
        'password' => 'password',
    ]);

    CatalogAssert::ok($response, ['token', 'user']);
    expect($response->json('data.user.email'))->toBe('admin@platform.sy')
        ->and($response->json('data.user.roles'))->toContain('platform_admin');
});

it('challenges 2fa then issues a token', function () {
    $secret = Totp::secret();
    $user = PlatformUser::factory()->create([
        'email' => '2fa@platform.sy',
        'password' => 'password',
        'two_factor_secret' => $secret,
    ]);
    $user->assignRole('platform_admin');

    $challenge = $this->postJson('/api/v1/platform/auth/login', [
        'email' => '2fa@platform.sy',
        'password' => 'password',
    ]);

    CatalogAssert::ok($challenge);
    expect($challenge->json('data.requires_2fa'))->toBeTrue()
        ->and($challenge->json('data.challenge_token'))->toStartWith('cht_');

    $verified = $this->postJson('/api/v1/platform/auth/2fa/verify', [
        'challenge_token' => $challenge->json('data.challenge_token'),
        'code' => totpNow($secret),
    ]);

    CatalogAssert::ok($verified, ['token', 'user']);
});

it('rejects invalid credentials', function () {
    PlatformUser::factory()->create(['email' => 'admin@platform.sy', 'password' => 'password']);

    CatalogAssert::error(
        $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@platform.sy',
            'password' => 'wrong',
        ]),
        401,
        'unauthenticated',
    );
});

it('exposes me, sessions, logout and doc-07 profile endpoints', function () {
    $user = PlatformUser::factory()->create(['password' => 'password']);
    $user->assignRole('platform_admin');
    Sanctum::actingAs($user, ['*'], 'platform');

    CatalogAssert::ok($this->getJson('/api/v1/platform/auth/me'), ['id', 'email', 'two_factor_enabled']);
    CatalogAssert::ok($this->getJson('/api/v1/platform/me'), ['id', 'email']);

    $this->putJson('/api/v1/platform/me', [
        'name' => 'Updated Admin',
        'phone' => '+963933000000',
    ])->assertOk()->assertJsonPath('data.updated', true);

    $this->postJson('/api/v1/platform/auth/confirm-password', ['password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.confirmed_until', fn ($v) => is_string($v) && $v !== '');

    $this->putJson('/api/v1/platform/me/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk()->assertJsonPath('data.updated', true);

    CatalogAssert::ok($this->getJson('/api/v1/platform/auth/sessions'));

    $this->postJson('/api/v1/platform/auth/logout')->assertOk()->assertJsonPath('data.success', true);
});

it('enables totp and reports recovery code count', function () {
    $user = PlatformUser::factory()->create();
    Sanctum::actingAs($user, ['*'], 'platform');

    $enable = $this->postJson('/api/v1/platform/me/2fa/enable');
    CatalogAssert::ok($enable, ['secret', 'qr_svg']);

    $pending = $user->refresh()->pending_two_factor_secret;
    expect($pending)->not->toBeEmpty();

    $confirm = $this->postJson('/api/v1/platform/me/2fa/confirm', [
        'code' => totpNow($pending),
    ]);
    CatalogAssert::ok($confirm);
    expect($confirm->json('data.enabled'))->toBeTrue()
        ->and($confirm->json('data.recovery_codes'))->toHaveCount(8);

    $this->getJson('/api/v1/platform/me/2fa/recovery-codes')
        ->assertOk()
        ->assertJsonPath('data.codes_remaining', 8);
});

it('recovery codes cannot be retrieved a second time by any route', function () {
    // Confirm returns plaintext once. The later endpoint returns codes_remaining only.
    $user = PlatformUser::factory()->create();
    Sanctum::actingAs($user, ['*'], 'platform');

    $enable = $this->postJson('/api/v1/platform/me/2fa/enable')->assertOk();
    $confirm = $this->postJson('/api/v1/platform/me/2fa/confirm', [
        'code' => totpNow((string) $user->refresh()->pending_two_factor_secret),
    ])->assertOk();

    expect($confirm->json('data.recovery_codes'))->toHaveCount(8);

    $again = $this->getJson('/api/v1/platform/me/2fa/recovery-codes')->assertOk();

    expect($again->json('data'))->toHaveKey('codes_remaining')
        ->and($again->json('data'))->not->toHaveKey('recovery_codes')
        ->and($again->json('data.codes_remaining'))->toBe(8)
        ->and($enable->json('data'))->not->toHaveKey('recovery_codes');
});

it('a used recovery code cannot be reused', function () {
    $user = PlatformUser::factory()->create([
        'email' => 'recovery@platform.sy',
        'password' => 'password',
    ]);
    $user->assignRole('platform_admin');
    Sanctum::actingAs($user, ['*'], 'platform');

    $this->postJson('/api/v1/platform/me/2fa/enable')->assertOk();
    $confirm = $this->postJson('/api/v1/platform/me/2fa/confirm', [
        'code' => totpNow((string) $user->refresh()->pending_two_factor_secret),
    ])->assertOk();

    $recoveryCode = (string) $confirm->json('data.recovery_codes.0');

    $this->app['auth']->forgetGuards();

    $challenge = $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'recovery@platform.sy',
        'password' => 'password',
    ])->assertOk();

    $this->postJson('/api/v1/platform/auth/2fa/verify', [
        'challenge_token' => $challenge->json('data.challenge_token'),
        'code' => $recoveryCode,
    ])->assertOk()->assertJsonPath('data.token', fn ($v) => is_string($v) && $v !== '');

    expect($user->refresh()->two_factor_recovery_codes)->toHaveCount(7);

    $challenge2 = $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'recovery@platform.sy',
        'password' => 'password',
    ])->assertOk();

    $this->postJson('/api/v1/platform/auth/2fa/verify', [
        'challenge_token' => $challenge2->json('data.challenge_token'),
        'code' => $recoveryCode,
    ])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'otp_invalid');
});

it('creates and deletes an api token after password confirm', function () {
    $user = PlatformUser::factory()->create(['password' => 'password']);
    Sanctum::actingAs($user, ['*'], 'platform');

    $created = $this->postJson('/api/v1/platform/me/api-tokens', [
        'name' => 'ci',
        'password_confirmation' => 'password',
    ]);
    CatalogAssert::ok($created, ['id', 'token']);

    CatalogAssert::ok($this->getJson('/api/v1/platform/me/api-tokens'));

    $this->deleteJson('/api/v1/platform/me/api-tokens/'.$created->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.success', true);
});
