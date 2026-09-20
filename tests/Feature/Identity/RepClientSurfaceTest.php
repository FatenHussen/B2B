<?php

declare(strict_types=1);

/**
 * Client-delivery surface for the remaining product screens: shop cards, zone list,
 * customer detail, and optional address/categories on register.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\RetailerProfile;
use Tests\Support\AppSurface;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('lists coverage shops with the shop card fields the client screens bind', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $profile = $shop->retailerProfile;
    $profile->forceFill([
        'address' => 'المزة فيلات شرقية',
        'lat' => 33.51,
        'lng' => 36.27,
    ])->save();
    Sanctum::actingAs(AppSurface::rep($channel, $refs), ['*'], 'app');

    $list = $this->getJson('/api/v1/app/rep/customers')->assertOk();
    $row = collect($list->json('data'))->firstWhere('id', $profile->id);

    expect($row)->not->toBeNull()
        ->and($row['shop_name'])->toBe($profile->shop_name)
        ->and($row['logo'])->toBeNull()
        ->and($row['zone_id'])->toBe($refs['zone']->id)
        ->and($row['zone'])->toBe('المزة')
        ->and($row['address'])->toBe('المزة فيلات شرقية')
        ->and($row['phone'])->toBe($shop->phone)
        ->and($row['lat'])->toBe(33.51)
        ->and($row['lng'])->toBe(36.27)
        ->and($row['is_open'])->toBeTrue()
        ->and($row['is_active'])->toBeTrue()
        ->and($row['last_order_at'])->toBeNull();
});

it('shows a coverage shop and 404s a shop outside coverage that the rep did not source', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $inZone = AppSurface::retailer($refs);
    $outZone = AppSurface::retailer($refs, $refs['otherZone']);
    Sanctum::actingAs(AppSurface::rep($channel, $refs), ['*'], 'app');

    $inId = AppSurface::retailerId($inZone);
    $outId = AppSurface::retailerId($outZone);

    $card = $this->getJson("/api/v1/app/rep/customers/{$inId}")->assertOk();
    expect($card->json('data.owner_name'))->toBe($inZone->name)
        ->and($card->json('data.activity_type_id'))->toBe($refs['activity']->id)
        ->and($card->json('data.categories'))->toContain($refs['root']->id)
        ->and($card->json('data.equipments'))->toBe([]);

    $this->getJson("/api/v1/app/rep/customers/{$outId}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('lists assigned zones with shops_count and persists address plus categories on register', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    Sanctum::actingAs(AppSurface::rep($channel, $refs), ['*'], 'app');

    $zones = $this->getJson('/api/v1/app/rep/zones')->assertOk();
    $row = collect($zones->json('data'))->firstWhere('id', $refs['zone']->id);
    expect($row)->not->toBeNull()
        ->and($row['name'])->toBe('المزة')
        ->and($row['governorate_id'])->toBe($refs['gov']->id)
        ->and($row['shops_count'])->toBe(0);

    $this->postJson('/api/v1/app/rep/customers', [
        'shop_name' => 'ميني ماركت الشام',
        'owner_name' => 'أبو سامر',
        'phone' => '+963988000011',
        'zone_id' => $refs['zone']->id,
        'activity_type_id' => $refs['activity']->id,
        'address' => 'المزة فيلات شرقية',
        'category_ids' => [$refs['root']->id],
        'equipment_ids' => [],
        'client_op_id' => 'op_shop_client_1',
    ])->assertCreated();

    $list = $this->getJson('/api/v1/app/rep/customers?filter[search]=ميني ماركت الشام')->assertOk();
    $id = (int) $list->json('data.0.id');
    expect($id)->toBeGreaterThan(0);

    $show = $this->getJson("/api/v1/app/rep/customers/{$id}")->assertOk();
    expect($show->json('data.address'))->toBe('المزة فيلات شرقية')
        ->and($show->json('data.owner_name'))->toBe('أبو سامر')
        ->and($show->json('data.categories'))->toContain($refs['root']->id);

    $after = $this->getJson('/api/v1/app/rep/zones')->assertOk();
    $counted = collect($after->json('data'))->firstWhere('id', $refs['zone']->id);
    expect($counted['shops_count'])->toBe(0);

    RetailerProfile::query()->whereKey($id)->update(['status' => ProfileStatus::Active->value]);
    $active = $this->getJson('/api/v1/app/rep/zones')->assertOk();
    expect(collect($active->json('data'))->firstWhere('id', $refs['zone']->id)['shops_count'])->toBe(1);
});
