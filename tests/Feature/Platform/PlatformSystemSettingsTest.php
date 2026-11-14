<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Models\PlatformSetting;
use Modules\Identity\Domain\Models\PlatformUser;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');
});

it('persists the selected OTP channel with its actor', function () {
    $this->postJson('/api/v1/platform/system/switch-otp-channel', ['channel' => 'sms'])
        ->assertOk()
        ->assertJsonPath('data.channel', 'sms');

    $setting = PlatformSetting::query()->where('group', 'otp')->where('key', 'channel')->sole();

    expect($setting->value)->toBe(['channel' => 'sms'])
        ->and((int) $setting->updated_by)->toBe((int) auth('platform')->id());
});

it('preserves integration providers and masks their credentials', function () {
    $this->postJson('/api/v1/platform/settings/integrations/sms/test')
        ->assertOk()
        ->assertJsonPath('data.ok', false);

    foreach (['sms', 'whatsapp'] as $provider) {
        $this->putJson("/api/v1/platform/settings/integrations/{$provider}", [
            'api_key' => "{$provider}-secret",
        ])->assertOk()->assertJsonPath('data.api_key', '***');
    }

    $setting = PlatformSetting::query()->where('group', 'integrations')->where('key', 'config')->sole();

    expect($setting->value)->toBe([
        'sms' => ['api_key' => 'sms-secret'],
        'whatsapp' => ['api_key' => 'whatsapp-secret'],
    ])->and((int) $setting->updated_by)->toBe((int) auth('platform')->id());

    $this->getJson('/api/v1/platform/settings/integrations')
        ->assertOk()
        ->assertJsonPath('data.sms', ['configured' => true, 'api_key' => '***'])
        ->assertJsonPath('data.whatsapp', ['configured' => true, 'api_key' => '***']);

    $this->postJson('/api/v1/platform/settings/integrations/sms/test')
        ->assertOk()
        ->assertJsonPath('data.ok', true);
});

it('preserves existing backup settings when recording a restore test', function () {
    PlatformSetting::query()->create([
        'group' => 'backup',
        'key' => 'config',
        'value' => ['retention_days' => 45, 'destination' => 's3://test-backups'],
    ]);

    $this->postJson('/api/v1/platform/system/backups/restore-test')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok');

    $setting = PlatformSetting::query()->where('group', 'backup')->where('key', 'config')->sole();

    expect($setting->value)->toMatchArray([
        'retention_days' => 45,
        'destination' => 's3://test-backups',
        'last_restore_test_status' => 'ok',
    ])->and($setting->value['last_restore_test_at'])->not->toBeEmpty()
        ->and((int) $setting->updated_by)->toBe((int) auth('platform')->id());
});
