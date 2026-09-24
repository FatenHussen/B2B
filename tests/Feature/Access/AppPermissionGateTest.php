<?php

declare(strict_types=1);

/**
 * BF-09: the seventeen `/app/*` routes whose catalog already names a permission must
 * reject a cross-kind caller with `403 insufficient_permission` and the permission key.
 *
 * App grants are kind-based (`AccessCatalog` / Gate::before), not Spatie roles on
 * AppUser. Middleware priority runs `permission:` before `app.kind` so the key is
 * named instead of a bare kind denial.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Tests\Support\AppSurface;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('names rp.delivery.deliver when a retailer hits rep deliveries', function () {
    Sanctum::actingAs(AppSurface::retailer(AppSurface::refs()), ['*'], 'app');

    $this->getJson('/api/v1/app/rep/deliveries')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'rp.delivery.deliver');
})->group('permissions');

it('names rp.delivery.accept when a retailer hits rep assignments', function () {
    Sanctum::actingAs(AppSurface::retailer(AppSurface::refs()), ['*'], 'app');

    $this->getJson('/api/v1/app/rep/assignments')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'rp.delivery.accept');
})->group('permissions');

it('names rp.warehouse.receive when a retailer hits warehouse receipts', function () {
    Sanctum::actingAs(AppSurface::retailer(AppSurface::refs()), ['*'], 'app');

    $this->getJson('/api/v1/app/rep/warehouse-receipts')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'rp.warehouse.receive');
})->group('permissions');

it('names rp.delivery.return_request when a retailer posts a rep return', function () {
    Sanctum::actingAs(AppSurface::retailer(AppSurface::refs()), ['*'], 'app');

    $this->postJson('/api/v1/app/rep/return-requests', [
        'sub_order_id' => 1,
        'reason' => 'تالف',
        'lines' => [],
    ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'rp.delivery.return_request');
})->group('permissions');

it('names rt.receive.confirm when a rep opens a retailer receipt', function () {
    $refs = AppSurface::refs();
    Sanctum::actingAs(AppSurface::rep(AppSurface::channel($refs), $refs), ['*'], 'app');

    $this->getJson('/api/v1/app/retailer/receipts/1')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'rt.receive.confirm');
})->group('permissions');

it('names rt.receive.return_request when a rep posts a retailer return', function () {
    $refs = AppSurface::refs();
    Sanctum::actingAs(AppSurface::rep(AppSurface::channel($refs), $refs), ['*'], 'app');

    $this->postJson('/api/v1/app/retailer/return-requests', [
        'sub_order_id' => 1,
        'reason' => 'تالف',
        'lines' => [],
    ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'rt.receive.return_request');
})->group('permissions');

it('names rp.payment.collect when a retailer hits rep payments', function () {
    Sanctum::actingAs(AppSurface::retailer(AppSurface::refs()), ['*'], 'app');

    $this->postJson('/api/v1/app/rep/payments', [])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'rp.payment.collect');
})->group('permissions');

it('names rt.payment.record when a rep hits retailer payments', function () {
    $refs = AppSurface::refs();
    Sanctum::actingAs(AppSurface::rep(AppSurface::channel($refs), $refs), ['*'], 'app');

    $this->postJson('/api/v1/app/retailer/payments', [])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'rt.payment.record');
})->group('permissions');

it('lets a same-kind caller through the permission gate', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $rep = AppSurface::rep($channel, $refs);
    $retailer = AppSurface::retailer($refs);

    Sanctum::actingAs($rep, ['*'], 'app');
    $this->getJson('/api/v1/app/rep/deliveries')->assertOk();
    $this->getJson('/api/v1/app/rep/assignments')->assertOk();
    $this->getJson('/api/v1/app/rep/warehouse-receipts')->assertOk();
    $this->getJson('/api/v1/app/rep/wallet')->assertOk();

    app('auth')->forgetGuards();
    Sanctum::actingAs($retailer, ['*'], 'app');
    $response = $this->getJson('/api/v1/app/retailer/receipts/1');
    expect($response->status())->not->toBe(403)
        ->and($response->json('error.permission'))->toBeNull();
})->group('permissions');
