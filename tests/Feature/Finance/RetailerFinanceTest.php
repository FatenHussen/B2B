<?php

declare(strict_types=1);

/**
 * AP-01 — EP-RT-050 … 054 retailer finance surface.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\Payment;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('records a retailer payment against a reserved receipt and clears the receivable', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $retailer = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    $retailerId = AppSurface::retailerId($retailer);
    $invoice = app(IssuesInvoice::class)->issue(random_int(1, 2_000_000_000), $channel->id, $retailerId, 48000, $rep->id);

    Sanctum::actingAs($rep, ['*'], 'app');
    $receiptNo = $this->postJson('/api/v1/app/receipts/reserve')->json('data.receipt_no');

    app('auth')->forgetGuards();
    Sanctum::actingAs($retailer, ['*'], 'app');

    $paid = $this->postJson('/api/v1/app/retailer/payments', [
        'receipt_no' => $receiptNo,
        'rep_id' => $rep->id,
        'supply_channel_id' => $channel->id,
        'invoice_no' => $invoice['no'],
        'amount' => 48000,
    ]);
    CatalogAssert::ok($paid);
    expect($paid->json('data.payment.id'))->toBeInt()
        ->and($paid->json('data.retailer_receivable'))->toBe(0)
        ->and(Tenant::as($channel->id, fn () => Payment::query()->where('receipt_no', $receiptNo)->value('source')))->toBe('retailer_app');
});

it('409s a duplicate receipt and hides another retailer payment as not_found on summary isolation', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $a = AppSurface::retailer($refs);
    $b = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    $aId = AppSurface::retailerId($a);
    $invoice = app(IssuesInvoice::class)->issue(random_int(1, 2_000_000_000), $channel->id, $aId, 12000, $rep->id);

    Sanctum::actingAs($rep, ['*'], 'app');
    $receiptNo = $this->postJson('/api/v1/app/receipts/reserve')->json('data.receipt_no');
    app('auth')->forgetGuards();
    Sanctum::actingAs($a, ['*'], 'app');
    CatalogAssert::ok($this->postJson('/api/v1/app/retailer/payments', [
        'receipt_no' => $receiptNo,
        'rep_id' => $rep->id,
        'supply_channel_id' => $channel->id,
        'invoice_no' => $invoice['no'],
        'amount' => 12000,
    ]));

    CatalogAssert::error($this->postJson('/api/v1/app/retailer/payments', [
        'receipt_no' => $receiptNo,
        'rep_id' => $rep->id,
        'supply_channel_id' => $channel->id,
        'invoice_no' => $invoice['no'],
        'amount' => 1000,
    ]), 409, 'duplicate_receipt_no');

    app('auth')->forgetGuards();
    Sanctum::actingAs($b, ['*'], 'app');
    $summary = $this->getJson('/api/v1/app/retailer/account/summary');
    CatalogAssert::ok($summary);
    expect($summary->json('data.debt_total'))->toBe(0)
        ->and($summary->json('data.payments_total'))->toBe(0);
});

it('matches debt_total to open invoices minus payments and lists debts by channel', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $retailer = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    $retailerId = AppSurface::retailerId($retailer);
    $invoice = app(IssuesInvoice::class)->issue(random_int(1, 2_000_000_000), $channel->id, $retailerId, 48000, $rep->id);

    Sanctum::actingAs($retailer, ['*'], 'app');

    $summary = $this->getJson('/api/v1/app/retailer/account/summary?month='.now()->format('Y-m'));
    CatalogAssert::ok($summary, ['debt_total', 'orders_total', 'points']);
    expect($summary->json('data.debt_total'))->toBe(48000)
        ->and($summary->json('data.orders_total'))->toBe(48000)
        ->and($summary->json('data.orders_count'))->toBe(1);

    $debts = $this->getJson('/api/v1/app/retailer/debts');
    CatalogAssert::ok($debts);
    expect($debts->json('data.by_channel.0.channel.id'))->toBe($channel->id)
        ->and($debts->json('data.by_channel.0.total'))->toBe(48000)
        ->and($debts->json('data.by_channel.0.invoices.0.no'))->toBe($invoice['no']);

    $statement = $this->getJson('/api/v1/app/retailer/account/statement?date_from='.now()->subMonth()->toDateString().'&date_to='.now()->toDateString());
    CatalogAssert::ok($statement, ['opening_balance', 'rows', 'closing_balance']);
    expect($statement->json('data.closing_balance'))->toBe(48000);

    $export = $this->postJson('/api/v1/app/retailer/account/statement/export', [
        'date_from' => now()->subMonth()->toDateString(),
        'date_to' => now()->toDateString(),
        'channel_id' => $channel->id,
        'format' => 'pdf',
    ]);
    CatalogAssert::ok($export);
    expect($export->json('data.job_id'))->toStartWith('job_stmt_');

    expect(Tenant::as($channel->id, fn () => Invoice::query()->find($invoice['id'])?->status))->toBe('open');
});

it('rejects retailer finance without a bearer', function () {
    CatalogAssert::error($this->getJson('/api/v1/app/retailer/account/summary'), 401, 'unauthenticated');
    CatalogAssert::error($this->getJson('/api/v1/app/retailer/debts'), 401, 'unauthenticated');
});

it('rejects a rep calling the retailer payment route', function () {
    $refs = AppSurface::refs();
    $rep = AppSurface::rep(AppSurface::channel($refs), $refs);
    Sanctum::actingAs($rep, ['*'], 'app');
    CatalogAssert::error($this->postJson('/api/v1/app/retailer/payments', [
        'receipt_no' => 'RCPT-X',
        'rep_id' => $rep->id,
        'supply_channel_id' => 1,
        'amount' => 1000,
    ]), 403, 'insufficient_permission');
});
