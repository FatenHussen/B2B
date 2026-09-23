<?php

declare(strict_types=1);

/**
 * Channel P1 pickers/shows: retailers, warehouses, product detail, invoice detail.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function channelSurfaceManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('lists coverage retailers and channel warehouses', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $retailer = AppSurface::retailer($refs);
    $manager = channelSurfaceManager($channel);

    Tenant::as($channel->id, function () use ($channel): void {
        Warehouse::query()->create([
            'channel_id' => $channel->id,
            'name' => 'مستودع المزة',
            'status' => WarehouseStatus::Active,
        ]);
    });

    Sanctum::actingAs($manager, ['*'], 'channel');

    $retailers = $this->getJson('/api/v1/channel/retailers');
    CatalogAssert::ok($retailers);
    expect(collect($retailers->json('data'))->pluck('id')->all())
        ->toContain(AppSurface::retailerId($retailer));

    $warehouses = $this->getJson('/api/v1/channel/warehouses');
    CatalogAssert::ok($warehouses);
    expect($warehouses->json('data.0.name'))->toBe('مستودع المزة');
});

it('shows a channel product and invoice detail with lines', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $retailer = AppSurface::retailer($refs);
    $manager = channelSurfaceManager($channel);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs, 'CH-SHOW-1');
    $issued = app(IssuesInvoice::class)->issue(
        random_int(1, 2_000_000_000),
        $channel->id,
        AppSurface::retailerId($retailer),
        48000,
        null,
    );

    Sanctum::actingAs($manager, ['*'], 'channel');

    $product = $this->getJson('/api/v1/channel/products/'.$productId);
    CatalogAssert::ok($product, ['id', 'sku', 'name_ar', 'availability']);
    expect($product->json('data.id'))->toBe($productId);

    $invoice = $this->getJson('/api/v1/channel/invoices/'.$issued['id']);
    CatalogAssert::ok($invoice, ['id', 'no', 'lines', 'remaining']);
    expect($invoice->json('data.total'))->toBe(48000)
        ->and($invoice->json('data.remaining'))->toBe(48000);
});

it('returns settings and coverage zones as catalogued orphans', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $manager = channelSurfaceManager($channel);
    Sanctum::actingAs($manager, ['*'], 'channel');

    $settings = $this->getJson('/api/v1/channel');
    CatalogAssert::ok($settings, ['id', 'name', 'status']);
    expect($settings->json('data.id'))->toBe($channel->id);

    $zones = $this->getJson('/api/v1/channel/zones');
    CatalogAssert::ok($zones);
    expect($zones->json('data'))->toBeArray();
});
