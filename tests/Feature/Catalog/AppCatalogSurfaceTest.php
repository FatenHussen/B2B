<?php

declare(strict_types=1);

/**
 * BE-C12 — the /app/* catalog routes with a product that has a brand and a category.
 *
 * Home, the product list, the product card, the brand card and the rep product list all
 * threw 500 channel_scope_required whenever a listed product had a brand: the root query
 * lifts the channel scope and filters by the retailer's channels, but `->with('brand')`
 * builds a fresh Brand query and Brand's own strict scope ran on it, with no tenant on
 * /app/*. Layer2Test never saw it because its products had no brand.
 *
 * Isolation on this surface is the shopping context — the active channels covering the
 * retailer's zone, or the rep's own channel — and it lives on the root query. The
 * relation cannot widen it: a product's brand is on the product's channel by
 * construction. The "other owner" here is a retailer in an uncovered zone and a rep on
 * another channel.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Tests\Support\AppSurface;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

// ─── retailer ───────────────────────────────────────────────────────────────────────

it('shows home with a branded product in a covered zone', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::retailer($refs), ['*'], 'app');

    $this->getJson('/api/v1/app/retailer/home')->assertOk();
});

it('lists branded products and shows one', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId, $brandId] = AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::retailer($refs), ['*'], 'app');

    $list = $this->getJson('/api/v1/app/retailer/products')->assertOk();
    expect(collect($list->json('data'))->pluck('id')->all())->toContain($productId);

    $card = $this->getJson("/api/v1/app/retailer/products/{$productId}")->assertOk();
    expect($card->json('data'))->not->toHaveKey('supply_channel')
        ->and($card->json('data.brand.id') ?? $card->json('data.brand_id'))->toBe($brandId);
});

it('shows a brand card with the categories its products fall in', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [, $brandId, $categoryId] = AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::retailer($refs), ['*'], 'app');

    $card = $this->getJson("/api/v1/app/retailer/brands/{$brandId}")->assertOk();

    expect(collect($card->json('data.categories'))->pluck('id')->all())->toContain($categoryId);
});

// ─── retailer: the other owner ──────────────────────────────────────────────────────

it('hides a branded product from a retailer in a zone the channel does not cover', function () {
    // The isolation is the shopping context on the root query, and lifting the scope on
    // the brand relation must not loosen it: out of coverage, the product is absent from
    // the list and 404 on the card, brand or no brand.
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId, $brandId] = AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::retailer($refs, $refs['otherZone']), ['*'], 'app');

    $list = $this->getJson('/api/v1/app/retailer/products')->assertOk();
    expect(collect($list->json('data'))->pluck('id')->all())->not->toContain($productId);

    $this->getJson("/api/v1/app/retailer/products/{$productId}")->assertNotFound();
    $this->getJson("/api/v1/app/retailer/brands/{$brandId}")->assertNotFound();
    $this->getJson('/api/v1/app/retailer/home')->assertOk();
});

// ─── rep ────────────────────────────────────────────────────────────────────────────

it('lists branded products to a rep on the channel, with the channel named', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::rep($channel, $refs), ['*'], 'app');

    $list = $this->getJson('/api/v1/app/rep/products')->assertOk();
    $row = collect($list->json('data'))->firstWhere('id', $productId);

    expect($row)->not->toBeNull()
        ->and($row['channel']['id'])->toBe($channel->id);
});

it('hides a branded product from a rep on another channel', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $otherChannel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::rep($otherChannel, $refs), ['*'], 'app');

    $list = $this->getJson('/api/v1/app/rep/products')->assertOk();

    expect(collect($list->json('data'))->pluck('id')->all())->not->toContain($productId);
});
