<?php

declare(strict_types=1);

/**
 * PA-18 — EP-AD-058 dual-gated channel deletion.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Support\Totp;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelEvent;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    config(['otp.bypass' => true]);
});

function deleteAdmin(string $password = 'password'): PlatformUser
{
    $admin = PlatformUser::factory()->create(['password' => $password]);
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

function archivedChannel(int $daysAgo = 31): SupplyChannel
{
    $channel = SupplyChannel::factory()->create([
        'name' => 'شركة النور',
        'status' => ChannelStatus::Archived,
    ]);

    ChannelEvent::query()->create([
        'channel_id' => $channel->id,
        'from_status' => ChannelStatus::Suspended,
        'to_status' => ChannelStatus::Archived,
        'actor_type' => PlatformUser::class,
        'actor_id' => 1,
        'reason' => 'test archive',
        'at' => now()->subDays($daysAgo),
    ]);

    return $channel;
}

it('opens a deletion request for an archived channel', function () {
    deleteAdmin();
    $channel = archivedChannel();

    $this->deleteJson("/api/v1/platform/channels/{$channel->id}", [
        'password_confirmation' => 'password',
        'otp_code' => '123456',
        'typed_name' => 'شركة النور',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['deletion_request_id']]);

    expect($channel->fresh())->not->toBeNull()
        ->and($channel->fresh()->trashed())->toBeFalse();
});

it('refuses deletion when not archived long enough', function () {
    deleteAdmin();
    $channel = archivedChannel(5);

    $this->deleteJson("/api/v1/platform/channels/{$channel->id}", [
        'password_confirmation' => 'password',
        'otp_code' => '123456',
        'typed_name' => 'شركة النور',
    ])->assertStatus(409);
});

it('completes deletion after a second approver', function () {
    $requester = deleteAdmin();
    $channel = archivedChannel();

    $first = $this->deleteJson("/api/v1/platform/channels/{$channel->id}", [
        'password_confirmation' => 'password',
        'otp_code' => '123456',
        'typed_name' => 'شركة النور',
    ])->assertOk()->json('data.deletion_request_id');

    $approver = PlatformUser::factory()->create(['password' => 'password']);
    $approver->assignRole('platform_admin');
    Sanctum::actingAs($approver, ['*'], 'platform');

    $this->deleteJson("/api/v1/platform/channels/{$channel->id}", [
        'password_confirmation' => 'password',
        'otp_code' => '123456',
        'typed_name' => 'شركة النور',
        'approval_request_id' => $first,
        'approval_reason' => 'اعتماد حذف القناة المؤرشفة',
    ])->assertOk()
        ->assertJsonPath('data.deletion_request_id', $first);

    expect(SupplyChannel::withTrashed()->find($channel->id)?->trashed())->toBeTrue();
    expect($requester->id)->not->toBe($approver->id);
});

it('rejects a wrong TOTP when bypass is off (BF-05)', function () {
    config(['otp.bypass' => false]);

    $secret = Totp::secret();
    $admin = PlatformUser::factory()->create([
        'password' => 'password',
        'two_factor_secret' => $secret,
    ]);
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $channel = archivedChannel();

    $this->postJson('/api/v1/platform/auth/request-otp', [
        'purpose' => 'platform_channel_delete',
    ])->assertOk()
        ->assertJsonPath('data.mode', 'totp');

    $this->deleteJson("/api/v1/platform/channels/{$channel->id}", [
        'password_confirmation' => 'password',
        'otp_code' => '000000',
        'typed_name' => 'شركة النور',
    ])->assertStatus(422);

    $code = Totp::at($secret, (int) floor(time() / 30));

    $this->deleteJson("/api/v1/platform/channels/{$channel->id}", [
        'password_confirmation' => 'password',
        'otp_code' => $code,
        'typed_name' => 'شركة النور',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['deletion_request_id']]);
});
