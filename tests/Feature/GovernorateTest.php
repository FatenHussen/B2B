<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('lists governorates for any authenticated user', function () {
    Governorate::factory()->count(3)->create();
    Sanctum::actingAs(AppUser::factory()->retailer()->create(), ['*'], 'app');

    $this->getJson('/api/v1/governorates')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('rejects an unauthenticated request', function () {
    $this->getJson('/api/v1/governorates')->assertUnauthorized();
});

it('lets a platform admin create a governorate', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $this->postJson('/api/v1/platform/refs/governorates', [
        'name_ar' => 'دمشق',
        'name_en' => 'Damascus',
        'code' => 'DAM',
    ])->assertCreated()
        ->assertJsonPath('data.code', 'DAM');

    expect(Governorate::where('code', 'DAM')->exists())->toBeTrue();
});

it('blocks a platform user without the reference-write permission', function () {
    // The route lives on the platform guard now (BE-R01), so the gate is measured with a
    // platform user who holds a neighbouring refs code but not `ad.refs.create`. A
    // warehouse keeper would fail the guard first and prove nothing about the gate.
    $viewer = PlatformUser::factory()->create();
    $viewer->givePermissionTo('ad.refs.view');
    Sanctum::actingAs($viewer, ['*'], 'platform');

    $this->postJson('/api/v1/platform/refs/governorates', [
        'name_ar' => 'حلب',
        'name_en' => 'Aleppo',
        'code' => 'ALP',
    ])->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.refs.create');
});

it('validates a unique code on update', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    Governorate::factory()->create(['code' => 'DAM']);
    $target = Governorate::factory()->create(['code' => 'ALP']);

    $this->putJson("/api/v1/platform/refs/governorates/{$target->id}", ['code' => 'DAM'])
        ->assertStatus(422)
        ->assertJsonPath('error.details.code', fn ($v) => is_array($v) && $v !== []);
});

it('has no route that deletes a governorate', function () {
    // This file used to hold `it('deletes a governorate')`, green, asserting the row was
    // gone. It was guarding a breach of rule 12 — a reference entity is never hard
    // deleted — and DOC-08 never defined an `ad.refs.delete` to gate one with. The route
    // is withdrawn rather than made to 403, so nothing has to remember why it is there.
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $governorate = Governorate::factory()->create();

    // 405, not 404: `/api/v1/platform/refs/governorates/{id}` still exists for GET and PUT, so Laravel
    // reports the method as unallowed rather than the path as missing. Either way there
    // is no handler, and the row survives — which is the assertion that matters.
    $this->deleteJson("/api/v1/platform/refs/governorates/{$governorate->id}")->assertStatus(405);

    expect(Governorate::find($governorate->id))->not->toBeNull();
});

it('disables a governorate instead, and reports what it affects', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $governorate = Governorate::factory()->create();
    Zone::factory()->count(3)->create(['governorate_id' => $governorate->id]);

    $this->patchJson("/api/v1/platform/refs/governorates/{$governorate->id}/status", [
        'status' => 'disabled',
        'reason' => 'إعادة ترسيم إداري',
    ])->assertOk()
        ->assertJsonPath('data.status', 'disabled')
        ->assertJsonPath('data.affected.zones', 3);

    // Disabled, not gone. That is the whole point of the endpoint.
    expect($governorate->refresh()->status)->toBe(RefStatus::Disabled);
});

it('refuses a status change with no reason', function () {
    // BE-R02 acceptance criterion: an update without a reason is refused with 422. The
    // catalog says the same on EP-AD-042A — every ref change carries a reason and is
    // audited before and after.
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $governorate = Governorate::factory()->create();

    $this->patchJson("/api/v1/platform/refs/governorates/{$governorate->id}/status", ['status' => 'disabled'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect($governorate->refresh()->status)->toBe(RefStatus::Active);
});

it('refuses a status the enum does not define', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $governorate = Governorate::factory()->create();

    $this->patchJson("/api/v1/platform/refs/governorates/{$governorate->id}/status", [
        'status' => 'inactive', // ZoneStatus has this case; RefStatus does not.
        'reason' => 'خطأ مطبعي',
    ])->assertStatus(422);

    expect($governorate->refresh()->status)->toBe(RefStatus::Active);
});

it('never lets a channel manager disable a governorate', function () {
    // `ad.refs.disable` is a platform code. The route group still admits four guards, so
    // the gate is what refuses — the same boundary the vocabulary batch established.
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');

    $governorate = Governorate::factory()->create();
    $token = $manager->createToken('disable-probe', ['*'])->plainTextToken;

    $this->patchJson("/api/v1/platform/refs/governorates/{$governorate->id}/status", [
        'status' => 'disabled',
        'reason' => 'محاولة',
    ], ['Authorization' => 'Bearer '.$token])->assertForbidden();

    expect($governorate->refresh()->status)->toBe(RefStatus::Active);
});
