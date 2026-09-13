<?php

declare(strict_types=1);

/**
 * BE-R09 — exchange rates: which one prevails, and that changing one never reprices an
 * order that already exists.
 */

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\FxRateResolver;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\FxRate;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function fxPair(): array
{
    $syp = Currency::query()->where('iso', 'SYP')->firstOrFail();
    $usd = Currency::query()->create([
        'iso' => 'USD',
        'name' => 'دولار',
        'symbol' => '$',
        'decimals' => 2,
        'is_display_currency' => false,
    ]);

    return [(int) $usd->id, (int) $syp->id];
}

it('takes the most recently created row among those in force', function () {
    // The overlap case, stated plainly: two rows on the same pair, both running now.
    // The rule is "newest row among those in force", so the correction wins — which is
    // the whole reason the rule is not "earliest effective_from".
    [$from, $to] = fxPair();

    $original = FxRate::query()->create([
        'from_currency_id' => $from,
        'to_currency_id' => $to,
        'rate' => 13_000 * Money::FX_UNIT,
        'effective_from' => now()->subDays(10),
        'effective_to' => now()->addDays(10),
    ]);

    // Entered later, starts later, same still-open window: a correction to a wrong rate.
    $correction = FxRate::query()->create([
        'from_currency_id' => $from,
        'to_currency_id' => $to,
        'rate' => 14_500 * Money::FX_UNIT,
        'effective_from' => now()->subDays(2),
        'effective_to' => now()->addDays(10),
    ]);

    $winner = app(FxRateResolver::class)->prevailing($from, $to);

    expect($winner->id)->toBe($correction->id)
        ->and($winner->id)->not->toBe($original->id)
        ->and($winner->rate)->toBe(14_500 * Money::FX_UNIT);
})->group('fx');

it('ignores a row whose window has not opened yet', function () {
    // The reason the rule is not simply "the last row entered": a rate scheduled for next
    // week must not override the one running today, however recently it was keyed in.
    [$from, $to] = fxPair();

    $running = FxRate::query()->create([
        'from_currency_id' => $from,
        'to_currency_id' => $to,
        'rate' => 13_000 * Money::FX_UNIT,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    FxRate::query()->create([
        'from_currency_id' => $from,
        'to_currency_id' => $to,
        'rate' => 99_000 * Money::FX_UNIT,
        'effective_from' => now()->addWeek(),
        'effective_to' => null,
    ]);

    expect(app(FxRateResolver::class)->prevailing($from, $to)->id)->toBe($running->id);
})->group('fx');

it('ignores a row whose window has closed', function () {
    [$from, $to] = fxPair();

    FxRate::query()->create([
        'from_currency_id' => $from,
        'to_currency_id' => $to,
        'rate' => 9_000 * Money::FX_UNIT,
        'effective_from' => now()->subMonth(),
        'effective_to' => now()->subDay(),
    ]);

    $running = FxRate::query()->create([
        'from_currency_id' => $from,
        'to_currency_id' => $to,
        'rate' => 13_000 * Money::FX_UNIT,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    expect(app(FxRateResolver::class)->prevailing($from, $to)->id)->toBe($running->id);
})->group('fx');

it('treats windows as half-open, so no instant belongs to two of them', function () {
    // effective_from inclusive, effective_to exclusive. At the seam the later row owns
    // the instant, and the earlier one is already finished.
    [$from, $to] = fxPair();
    $seam = now()->startOfSecond();

    FxRate::query()->create([
        'from_currency_id' => $from,
        'to_currency_id' => $to,
        'rate' => 9_000 * Money::FX_UNIT,
        'effective_from' => $seam->copy()->subDay(),
        'effective_to' => $seam,
    ]);

    $next = FxRate::query()->create([
        'from_currency_id' => $from,
        'to_currency_id' => $to,
        'rate' => 13_000 * Money::FX_UNIT,
        'effective_from' => $seam,
        'effective_to' => null,
    ]);

    expect(app(FxRateResolver::class)->prevailing($from, $to, $seam)->id)->toBe($next->id);
})->group('fx');

it('refuses to invent a rate when no row is in force', function () {
    // A conversion that cannot find a rate fails. It never falls back to 1.0, which
    // would silently price a foreign currency as though it were the base one.
    [$from, $to] = fxPair();

    expect(fn () => app(FxRateResolver::class)->prevailing($from, $to))
        ->toThrow(DomainException::class);

    expect(app(FxRateResolver::class)->find($from, $to))->toBeNull();
})->group('fx');

it('never reprices an existing order when the rate changes', function () {
    // BR-AD-19, read through the route a retailer actually calls rather than through the
    // model. Reading the model would only prove a column did not change; reading the
    // route proves the user is not shown a different amount.
    [$from, $to] = fxPair();

    FxRate::query()->create([
        'from_currency_id' => $from,
        'to_currency_id' => $to,
        'rate' => 13_000 * Money::FX_UNIT,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    $channel = SupplyChannel::factory()->create();
    $governorate = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $governorate->id]);
    $activityTypeId = DB::table('activity_types')->insertGetId([
        'name' => 'بقالة', 'order' => 0, 'status' => RefStatus::Active->value,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $user = AppUser::factory()->retailer()->create();
    $retailerId = (int) DB::table('retailer_profiles')->insertGetId([
        'app_user_id' => $user->id,
        'shop_name' => 'متجر الاختبار',
        'activity_type_id' => $activityTypeId,
        'governorate_id' => $governorate->id,
        'zone_id' => $zone->id,
        'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $orderId = (int) DB::table('orders')->insertGetId([
        'retailer_id' => $retailerId, 'source' => 'app', 'order_no' => 'ORD-FX-1',
        'status' => 'pending', 'currency' => 'SYP',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $subOrderId = (int) DB::table('sub_orders')->insertGetId([
        'order_id' => $orderId, 'channel_id' => $channel->id, 'retailer_id' => $retailerId,
        'zone_id' => $zone->id, 'source' => 'app', 'sub_order_no' => 'SO-FX-1',
        'status' => 'pending', 'subtotal' => 500_000, 'discount' => 0, 'total' => 500_000,
        'currency_code' => 'SYP', 'fx_rate' => Money::FX_UNIT,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('sub_order_lines')->insert([
        'sub_order_id' => $subOrderId, 'product_id' => 1, 'qty' => 2,
        'unit_price' => 250_000, 'discount' => 0, 'line_total' => 500_000,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Sanctum::actingAs($user, ['*'], 'app');

    $before = $this->getJson("/api/v1/app/retailer/orders/{$subOrderId}")->assertOk();
    $priceBefore = $before->json('data.lines.0.price');

    expect($priceBefore)->toBe(250_000);

    // The rate more than doubles, in a window that is open right now.
    FxRate::query()->create([
        'from_currency_id' => $from,
        'to_currency_id' => $to,
        'rate' => 30_000 * Money::FX_UNIT,
        'effective_from' => now(),
        'effective_to' => null,
    ]);

    expect(app(FxRateResolver::class)->prevailing($from, $to)->rate)
        ->toBe(30_000 * Money::FX_UNIT);

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($user->fresh(), ['*'], 'app');

    $after = $this->getJson("/api/v1/app/retailer/orders/{$subOrderId}")->assertOk();

    // The user sees the same amount, and the frozen rate on the sub-order is untouched.
    expect($after->json('data.lines.0.price'))->toBe($priceBefore)
        ->and((int) DB::table('sub_orders')->where('id', $subOrderId)->value('fx_rate'))
        ->toBe(Money::FX_UNIT)
        ->and(DB::table('sub_orders')->where('id', $subOrderId)->value('currency_code'))
        ->toBe('SYP');
})->group('fx');
