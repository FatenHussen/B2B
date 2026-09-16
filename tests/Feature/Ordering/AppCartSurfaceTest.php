<?php

declare(strict_types=1);

/**
 * BE-C12 — the /app/* cart, submit, reorder, scheduled-orders and cancel routes: they
 * answer, and they answer only to their owner.
 *
 * Every one of these threw 500 channel_scope_required before this ticket: `/app/*` sets
 * no tenant, `CartSection`, `OrderSection` and `SubOrder` are strict, and the reads ran
 * through `Cart->sections`, `Order->subOrders` or a direct query with no escape. No test
 * noticed because none held a cart with a section in it. These do.
 *
 * Isolation on this surface is ownership — the cart belongs to the app user, the order
 * to the retailer, the postponed delivery to the rep — and `channel_id` is the split key
 * of a multi-channel cart. The "other owner" tests are the ones that matter.
 *
 * Where two users act in one test the guards are forgotten in between (the caching
 * hazard recorded in CrossGuardTest); otherwise one user per test.
 */

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Tests\Support\AppSurface;
use Tests\TestCase;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * A retailer holding a cart with one section and one line of `$productId`.
 *
 * @return array{0: int, 1: string} line id, section ref
 */
function cartWithOneLine(TestCase $t, AppUser $retailer, int $productId): array
{
    Sanctum::actingAs($retailer, ['*'], 'app');
    $cart = $t->postJson('/api/v1/app/retailer/cart/lines', ['product_id' => $productId, 'qty' => 2])->assertOk();

    return [(int) $cart->json('data.sections.0.lines.0.id'), (string) $cart->json('data.sections.0.supply_channel_ref')];
}

function switchUser(AppUser $user): void
{
    app('auth')->forgetGuards();
    Sanctum::actingAs($user, ['*'], 'app');
}

// ─── retailer cart ──────────────────────────────────────────────────────────────────

it('adds a line and shows the cart with its section', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    $retailer = AppSurface::retailer($refs);
    Sanctum::actingAs($retailer, ['*'], 'app');

    $this->postJson('/api/v1/app/retailer/cart/lines', ['product_id' => $productId, 'qty' => 2])
        ->assertOk()
        ->assertJsonCount(1, 'data.sections')
        ->assertJsonPath('data.sections.0.lines.0.qty', 2);

    $this->getJson('/api/v1/app/retailer/cart')
        ->assertOk()
        ->assertJsonCount(1, 'data.sections')
        ->assertJsonPath('data.sections.0.lines.0.product_id', $productId);
});

it('updates a line, a section note, and removes the line', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    $retailer = AppSurface::retailer($refs);
    [$lineId, $ref] = cartWithOneLine($this, $retailer, $productId);

    $this->patchJson("/api/v1/app/retailer/cart/lines/{$lineId}", ['qty' => 3])
        ->assertOk()
        ->assertJsonPath('data.sections.0.lines.0.qty', 3);

    $this->patchJson("/api/v1/app/retailer/cart/sections/{$ref}", ['note' => 'الاتصال قبل الوصول'])
        ->assertOk()
        ->assertJsonPath('data.sections.0.note', 'الاتصال قبل الوصول');

    $this->deleteJson("/api/v1/app/retailer/cart/lines/{$lineId}")->assertOk();

    expect(DB::table('cart_lines')->where('id', $lineId)->exists())->toBeFalse();
});

it('submits the cart into a pending sub-order', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    $retailer = AppSurface::retailer($refs);
    cartWithOneLine($this, $retailer, $productId);

    $this->postJson('/api/v1/app/retailer/cart/submit', [])
        ->assertOk()
        ->assertJsonPath('data.order.sub_orders.0.status', 'pending');

    expect(DB::table('sub_orders')->where('retailer_id', AppSurface::retailerId($retailer))->count())->toBe(1)
        ->and(DB::table('sub_orders')->where('retailer_id', AppSurface::retailerId($retailer))->value('channel_id'))->toBe($channel->id);
});

it('reorders a delivered order into a fresh cart', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    $retailer = AppSurface::retailer($refs);
    $delivered = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($retailer), 'delivered', null, $productId);
    Sanctum::actingAs($retailer, ['*'], 'app');

    $this->postJson("/api/v1/app/retailer/orders/{$delivered}/reorder", [])
        ->assertOk()
        ->assertJsonPath('data.cart.sections.0.lines.0.product_id', $productId);
});

// ─── retailer cart: the other owner ─────────────────────────────────────────────────

it('never shows one retailer another retailer cart', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    $a = AppSurface::retailer($refs);
    $b = AppSurface::retailer($refs);
    cartWithOneLine($this, $b, $productId);

    switchUser($a);

    $this->getJson('/api/v1/app/retailer/cart')
        ->assertOk()
        ->assertJsonCount(0, 'data.sections');
});

it('never lets a retailer touch another retailer line or section', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    $a = AppSurface::retailer($refs);
    $b = AppSurface::retailer($refs);
    [$bLine, $bRef] = cartWithOneLine($this, $b, $productId);

    switchUser($a);

    $this->patchJson("/api/v1/app/retailer/cart/lines/{$bLine}", ['qty' => 9])->assertNotFound();
    $this->deleteJson("/api/v1/app/retailer/cart/lines/{$bLine}")->assertNotFound();
    $this->patchJson("/api/v1/app/retailer/cart/sections/{$bRef}", ['note' => 'x'])->assertNotFound();

    expect((int) DB::table('cart_lines')->where('id', $bLine)->value('qty'))->toBe(2);
});

// ─── cancel through the route ───────────────────────────────────────────────────────

it('lets a retailer cancel their own pending order through the route', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $a = AppSurface::retailer($refs);
    $own = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($a));
    Sanctum::actingAs($a, ['*'], 'app');

    $this->postJson("/api/v1/app/retailer/orders/{$own}/cancel", ['reason' => 'تغيّر رأيي'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('hides another retailer order from cancel behind 404', function () {
    // The escape landed on this route only after the retailer_id filter did (1bc4878);
    // this is the route-level half of that proof: 404, nothing moved, nothing logged.
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $a = AppSurface::retailer($refs);
    $b = AppSurface::retailer($refs);
    $bOrder = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($b));
    Sanctum::actingAs($a, ['*'], 'app');

    $this->postJson("/api/v1/app/retailer/orders/{$bOrder}/cancel", ['reason' => 'ليس طلبي'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    expect(DB::table('sub_orders')->where('id', $bOrder)->value('status'))->toBe('pending')
        ->and(DB::table('sub_order_events')->where('sub_order_id', $bOrder)->count())->toBe(0);
});

// ─── rep cart ───────────────────────────────────────────────────────────────────────

/**
 * A rep's own customer, registered through the rep route. The route answers the
 * rep-sourced shop id; the cart wants the retailer profile behind it.
 *
 * @param  array<string, mixed>  $refs
 */
function repCustomer(TestCase $t, array $refs, string $phone): int
{
    $shopId = (int) $t->postJson('/api/v1/app/rep/customers', [
        'shop_name' => 'محل الزبون '.$phone,
        'owner_name' => 'أبو علي',
        'phone' => $phone,
        'zone_id' => $refs['zone']->id,
        'activity_type_id' => $refs['activity']->id,
        'client_op_id' => 'op-'.$phone,
    ])->assertCreated()->json('data.id');

    return (int) DB::table('rep_sourced_shops')->where('id', $shopId)->value('retailer_id');
}

it('lets a rep build a customer section and submit it', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    $rep = AppSurface::rep($channel, $refs);
    Sanctum::actingAs($rep, ['*'], 'app');
    $customer = repCustomer($this, $refs, '+963944000101');

    $this->postJson('/api/v1/app/rep/cart/lines', ['retailer_id' => $customer, 'product_id' => $productId, 'qty' => 1])
        ->assertOk();

    $this->getJson('/api/v1/app/rep/cart')->assertOk();

    $this->postJson("/api/v1/app/rep/cart/sections/{$customer}/submit", [])
        ->assertOk()
        ->assertJsonPath('data.sub_order.status', 'pending');

    expect(DB::table('sub_orders')->where('retailer_id', $customer)->where('channel_id', $channel->id)->count())->toBe(1);
});

it('never shows one rep another rep cart, and never submits another rep section', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    $repA = AppSurface::rep($channel, $refs);
    $repB = AppSurface::rep($channel, $refs);

    Sanctum::actingAs($repA, ['*'], 'app');
    $customer = repCustomer($this, $refs, '+963944000102');
    $this->postJson('/api/v1/app/rep/cart/lines', ['retailer_id' => $customer, 'product_id' => $productId, 'qty' => 1])->assertOk();

    switchUser($repB);

    $this->getJson('/api/v1/app/rep/cart')->assertOk()->assertJsonCount(0, 'data.sections');
    // Rep B has no section for that customer: the submit finds an empty cart, not A's.
    $this->postJson("/api/v1/app/rep/cart/sections/{$customer}/submit", [])->assertStatus(422);

    expect(DB::table('sub_orders')->where('retailer_id', $customer)->count())->toBe(0);
});

// ─── rep scheduled orders ───────────────────────────────────────────────────────────

it('lists only the rep own postponed orders', function () {
    // The catalog row carries the shop, not the sub-order id, so ownership is read off
    // the shop names: rep A sees the delivery scheduled for shop A and not shop B's.
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shopA = AppSurface::retailer($refs);
    $shopB = AppSurface::retailer($refs);
    $repA = AppSurface::rep($channel, $refs);
    $repB = AppSurface::rep($channel, $refs);
    AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shopA), 'postponed', $repA->id);
    AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shopB), 'postponed', $repB->id);
    Sanctum::actingAs($repA, ['*'], 'app');

    $shops = collect($this->getJson('/api/v1/app/rep/scheduled-orders')->assertOk()->json('data'))->pluck('shop')->all();

    expect($shops)->toBe(['محل '.$shopA->id])->not->toContain('محل '.$shopB->id);
});
