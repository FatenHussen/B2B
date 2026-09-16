<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\ChannelFinanceMetrics;
use Modules\Core\Contracts\ChannelOrderMetrics;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Reporting\Application\Jobs\GenerateDailySnapshot;
use Modules\Reporting\Domain\Models\DailySnapshot;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function rptManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('reads dashboard figures from the latest snapshot rather than live joins', function () {
    $channel = SupplyChannel::factory()->create();
    Tenant::as($channel->id, function () use ($channel): void {
        DailySnapshot::query()->create([
            'supply_channel_id' => $channel->id,
            'snapshot_date' => '2026-09-14',
            'payload' => [
                'kpis' => [
                    'sales' => 62000000,
                    'orders_by_status' => ['pending' => 12],
                    'cash_collected' => 18000000,
                    'receivables' => ['total' => 42000000, 'overdue' => 6000000],
                    'retailers' => ['active' => 420, 'registered' => 510, 'new' => 14],
                    'avg_order_value' => 38000,
                    'avg_confirm_time' => 11,
                    'avg_delivery_time' => 140,
                    'fill_rate' => 9700,
                ],
                'alerts' => [['type' => 'waiting_orders', 'count' => 8, 'action_url' => '/orders']],
                'charts' => ['daily_sales' => [], 'by_zone' => [], 'top_products' => [], 'top_retailers' => [], 'rep_performance' => [], 'heatmap' => []],
                'reports' => ['sales' => ['rows' => [['zone_id' => 12]], 'totals' => ['sales' => 1]]],
                'margins' => ['by_product' => [['product_id' => 1]], 'by_zone' => []],
            ],
        ]);
    });

    Sanctum::actingAs(rptManager($channel), ['*'], 'channel');
    $dash = $this->getJson('/api/v1/channel/dashboard');
    CatalogAssert::ok($dash);
    expect($dash->json('data.kpis.sales'))->toBe(62000000)
        ->and($dash->json('data.kpis.fill_rate'))->toBe(9700)
        ->and($dash->json('meta.snapshot_date'))->toBe('2026-09-14');

    $report = $this->getJson('/api/v1/channel/reports/sales');
    CatalogAssert::ok($report);
    expect($report->json('data.type'))->toBe('sales')
        ->and($report->json('data.rows.0.zone_id'))->toBe(12);

    $margins = $this->getJson('/api/v1/channel/reports/margins');
    CatalogAssert::ok($margins);
    expect($margins->json('data.by_product.0.product_id'))->toBe(1);

    $export = $this->postJson('/api/v1/channel/reports/sales/export', [
        'format' => 'xlsx',
        'filters' => ['zone_id' => 12],
    ]);
    CatalogAssert::ok($export);
    expect($export->json('data.job_id'))->toStartWith('job_rep_exp_');
});

it('hides another channel snapshot from the dashboard', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    Tenant::as($foreign->id, function () use ($foreign): void {
        DailySnapshot::query()->create([
            'supply_channel_id' => $foreign->id,
            'snapshot_date' => '2026-09-14',
            'payload' => ['kpis' => ['sales' => 99]],
        ]);
    });

    Sanctum::actingAs(rptManager($own), ['*'], 'channel');
    $dash = $this->getJson('/api/v1/channel/dashboard');
    CatalogAssert::ok($dash);
    expect($dash->json('data.kpis.sales'))->toBe(0);
})->group('tenancy');

it('writes a snapshot from order and finance contracts onto the reports queue', function () {
    $channel = SupplyChannel::factory()->create();
    $job = new GenerateDailySnapshot($channel->id, '2026-09-14');
    expect($job->queue)->toBe('reports');
    $job->handle(app(ChannelOrderMetrics::class), app(ChannelFinanceMetrics::class));

    $row = Tenant::as($channel->id, fn () => DailySnapshot::query()->first());
    expect($row)->not->toBeNull()
        ->and($row?->payload['kpis']['sales'])->toBe(0)
        ->and($row?->payload['reports']['sales']['totals']['sales'])->toBe(0)
        ->and($row?->payload['charts']['daily_sales'][0]['date'])->toBe('2026-09-14');
});

it('queues a snapshot job per supply channel', function () {
    Bus::fake();
    SupplyChannel::factory()->count(2)->create();

    $this->artisan('reports:daily-snapshots', ['--date' => '2026-09-14'])->assertSuccessful();

    Bus::assertDispatched(GenerateDailySnapshot::class, 2);
});
