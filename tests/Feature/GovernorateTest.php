<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Models\WarehouseUser;
use Modules\Reference\Domain\Models\Governorate;

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

    $this->postJson('/api/v1/governorates', [
        'name_ar' => 'دمشق',
        'name_en' => 'Damascus',
        'code' => 'DAM',
    ])->assertCreated()
        ->assertJsonPath('data.code', 'DAM');

    expect(Governorate::where('code', 'DAM')->exists())->toBeTrue();
});

it('blocks a role without settings permission from writing', function () {
    $keeper = WarehouseUser::factory()->create();
    $keeper->assignRole('warehouse_keeper');
    Sanctum::actingAs($keeper, ['*'], 'warehouse');

    $this->postJson('/api/v1/governorates', [
        'name_ar' => 'حلب',
        'name_en' => 'Aleppo',
        'code' => 'ALP',
    ])->assertForbidden();
});

it('validates a unique code on update', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    Governorate::factory()->create(['code' => 'DAM']);
    $target = Governorate::factory()->create(['code' => 'ALP']);

    $this->putJson("/api/v1/governorates/{$target->id}", ['code' => 'DAM'])
        ->assertStatus(422)
        ->assertJsonPath('error.details.code', fn ($v) => is_array($v) && $v !== []);
});

it('deletes a governorate', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $governorate = Governorate::factory()->create();

    $this->deleteJson("/api/v1/governorates/{$governorate->id}")->assertNoContent();

    expect(Governorate::find($governorate->id))->toBeNull();
});
