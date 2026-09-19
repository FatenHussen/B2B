<?php

declare(strict_types=1);

/**
 * A delivery is read and moved only by its two parties.
 *
 * `postpone` and `fail` already asked `assertOwned()` whether the caller was the rep the
 * sub-order is assigned to. `detail`, `patchLine` and `complete` did not: any rep with a
 * sub-order id could read another rep's lines, change the delivered quantities — which
 * rewrite the invoice total — and complete the delivery in their name. The three now
 * start at the same check, and the check also knows the sub-order's retailer, because
 * the receipt routes share those methods.
 *
 * A stranger gets 404, never 403: existence is not disclosed (CLAUDE.md rule 11).
 */

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Delivery\Domain\Models\Delivery;
use Modules\Identity\Domain\Models\AppUser;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;
use Tests\TestCase;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * A sub-order on the way with rep A, its delivery row and one delivery line, plus a
 * second rep B in the same channel who has nothing to do with it.
 *
 * @return array{id: int, line_id: int, repA: AppUser, repB: AppUser, shop: AppUser, refs: array<string, mixed>}
 */
function deliveryOfRepA(TestCase $t): array
{
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $repA = AppSurface::rep($channel, $refs);
    $repB = AppSurface::rep($channel, $refs);
    $id = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'on_the_way', $repA->id);

    // The owner reads it once so the delivery and its lines exist with real ids.
    Sanctum::actingAs($repA, ['*'], 'app');
    $detail = $t->getJson("/api/v1/app/rep/deliveries/{$id}");
    CatalogAssert::ok($detail);

    return [
        'id' => $id,
        'line_id' => (int) $detail->json('data.lines.0.id'),
        'repA' => $repA,
        'repB' => $repB,
        'shop' => $shop,
        'refs' => $refs,
    ];
}

it('404s detail for another rep', function () {
    $d = deliveryOfRepA($this);
    Sanctum::actingAs($d['repB'], ['*'], 'app');

    CatalogAssert::error($this->getJson("/api/v1/app/rep/deliveries/{$d['id']}"), 404, 'not_found');
})->group('tenancy');

it('404s patchLine for another rep and leaves the line and the order quantity untouched', function () {
    $d = deliveryOfRepA($this);
    $before = DB::table('delivery_lines')->where('id', $d['line_id'])->first();
    Sanctum::actingAs($d['repB'], ['*'], 'app');

    CatalogAssert::error(
        $this->patchJson("/api/v1/app/rep/deliveries/{$d['id']}/lines/{$d['line_id']}", [
            'qty_delivered' => 0,
            'action' => 'return',
            'reason' => 'ليس لي',
        ]),
        404,
        'not_found',
    );

    $after = DB::table('delivery_lines')->where('id', $d['line_id'])->first();
    expect($after->qty_delivered)->toBe($before->qty_delivered)
        ->and($after->action)->toBe($before->action)
        ->and(DB::table('sub_order_lines')->where('id', $before->sub_order_line_id)->value('qty'))->toBe(1);
})->group('tenancy');

it('404s complete for another rep and does not deliver the order', function () {
    $d = deliveryOfRepA($this);
    Sanctum::actingAs($d['repB'], ['*'], 'app');

    CatalogAssert::error(
        $this->postJson("/api/v1/app/rep/deliveries/{$d['id']}/complete", [
            'lines' => [['line_id' => $d['line_id'], 'qty_delivered' => 1, 'action' => 'accept']],
        ]),
        404,
        'not_found',
    );

    expect(DB::table('sub_orders')->where('id', $d['id'])->value('status'))->toBe('on_the_way')
        ->and(Delivery::query()->where('sub_order_id', $d['id'])->value('status'))->not->toBe('delivered');
})->group('tenancy');

it('still lets the assigned rep adjust a line and reprice', function () {
    $d = deliveryOfRepA($this);

    $response = $this->patchJson("/api/v1/app/rep/deliveries/{$d['id']}/lines/{$d['line_id']}", [
        'qty_delivered' => 0,
        'action' => 'return',
        'reason' => 'تالف',
    ]);
    CatalogAssert::ok($response);

    expect($response->json('data.new_invoice_total'))->toBe(0)
        ->and(DB::table('delivery_lines')->where('id', $d['line_id'])->value('qty_delivered'))->toBe(0);
});

it('404s the receipt, its line and its confirmation for another retailer, and serves them to the buyer', function () {
    $d = deliveryOfRepA($this);
    $otherShop = AppSurface::retailer($d['refs']);

    Sanctum::actingAs($otherShop, ['*'], 'app');
    CatalogAssert::error($this->getJson("/api/v1/app/retailer/receipts/{$d['id']}"), 404, 'not_found');
    CatalogAssert::error(
        $this->patchJson("/api/v1/app/retailer/receipts/{$d['id']}/lines/{$d['line_id']}", [
            'qty_received' => 0,
            'action' => 'return',
        ]),
        404,
        'not_found',
    );
    CatalogAssert::error(
        $this->postJson("/api/v1/app/retailer/receipts/{$d['id']}/confirm", [
            'lines' => [['line_id' => $d['line_id'], 'qty_received' => 1, 'action' => 'accept']],
        ]),
        404,
        'not_found',
    );

    Sanctum::actingAs($d['shop'], ['*'], 'app');
    $mine = $this->getJson("/api/v1/app/retailer/receipts/{$d['id']}");
    CatalogAssert::ok($mine);
    expect($mine->json('data.lines.0.id'))->toBe($d['line_id']);
})->group('tenancy');
