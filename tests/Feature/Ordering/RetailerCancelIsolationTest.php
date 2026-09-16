<?php

declare(strict_types=1);

/**
 * BE-C12, condition 1 — `CancelSubOrder` on the retailer route carries an owner filter,
 * proved before the channel scope is touched.
 *
 * The route answers 500 today (strict scope, no tenant on /app/*), so the proof cannot go
 * through it yet. It goes through the action with the scope lifted — `Tenant::withoutScope`
 * is exactly what `acrossChannels()` will do in the next commit — so what is asserted is the
 * filter alone: with channel isolation gone, retailer A still cannot cancel retailer B's
 * order. Without the filter this test cancels B's order and fails; that is its red.
 *
 * The route-level proof (404, untouched) lands with the escape.
 */

use Illuminate\Support\Facades\DB;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Ordering\Application\Actions\CancelSubOrder;
use Tests\Support\AppSurface;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('refuses another retailer cancelling through the action even with the channel scope lifted', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $a = AppSurface::retailer($refs);
    $b = AppSurface::retailer($refs);
    $bOrder = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($b));

    $attempt = fn () => Tenant::withoutScope(
        fn () => app(CancelSubOrder::class)($a, $bOrder, ['reason' => 'ليس طلبي'], true)
    );

    expect($attempt)->toThrow(DomainException::class);

    $row = DB::table('sub_orders')->where('id', $bOrder)->first(['status', 'retailer_id']);
    expect($row->status)->toBe('pending')
        ->and((int) $row->retailer_id)->toBe(AppSurface::retailerId($b))
        ->and(DB::table('sub_order_events')->where('sub_order_id', $bOrder)->count())->toBe(0);
});

it('lets a retailer cancel their own pending order through the action', function () {
    // The control: the filter refuses the other owner, not everyone.
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $a = AppSurface::retailer($refs);
    $aOrder = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($a));

    $result = Tenant::withoutScope(
        fn () => app(CancelSubOrder::class)($a, $aOrder, ['reason' => 'تغيّر رأيي'], true)
    );

    expect($result['status'])->toBe('cancelled')
        ->and(DB::table('sub_orders')->where('id', $aOrder)->value('status'))->toBe('cancelled');
});

it('leaves the channel route unchanged: a channel user cancels within their tenant', function () {
    // The same action serves the channel route, where the tenant scope is the isolation
    // and no retailer filter applies. Under a tenant, the channel cancels its own order
    // exactly as before.
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $a = AppSurface::retailer($refs);
    $aOrder = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($a));
    $manager = ChannelUser::factory()->forChannel($channel)->create();

    $result = Tenant::as($channel->id, fn () => app(CancelSubOrder::class)($manager, $aOrder, ['reason' => 'نفاد المخزون']));

    expect($result['status'])->toBe('cancelled');
});
