<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\WalletTransaction;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * @return array{channel: SupplyChannel, retailer_id: int, rep: AppUser, invoice: array{id: int, no: string, total: int}}
 */
function repWalletWorld(): array
{
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $retailer = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    $retailerId = AppSurface::retailerId($retailer);
    $issued = app(IssuesInvoice::class)->issue(random_int(1, 2_000_000_000), $channel->id, $retailerId, 48000, $rep->id);

    return ['channel' => $channel, 'retailer_id' => $retailerId, 'rep' => $rep, 'invoice' => $issued];
}

function switchAppUser(AppUser $user): void
{
    app('auth')->forgetGuards();
    Sanctum::actingAs($user, ['*'], 'app');
}

it('reserves a receipt then collects into the wallet and the invoice', function () {
    $world = repWalletWorld();
    switchAppUser($world['rep']);

    $reserved = $this->postJson('/api/v1/app/receipts/reserve');
    CatalogAssert::ok($reserved);
    $receiptNo = $reserved->json('data.receipt_no');
    expect($receiptNo)->toStartWith('RCPT-');

    $paid = $this->postJson('/api/v1/app/rep/payments', [
        'receipt_no' => $receiptNo,
        'retailer_id' => $world['retailer_id'],
        'invoice_no' => $world['invoice']['no'],
        'amount' => 48000,
        'paid_at' => '2026-03-01T12:08:00+03:00',
        'client_op_id' => 'op_rep_pay_10041',
    ]);
    CatalogAssert::ok($paid);
    expect($paid->json('data.payment.id'))->toBeInt()
        ->and($paid->json('data.wallet_balance'))->toBe(48000)
        ->and($paid->json('data.retailer_receivable'))->toBe(0);

    $wallet = $this->getJson('/api/v1/app/rep/wallet');
    CatalogAssert::ok($wallet);
    expect($wallet->json('data.net_balance'))->toBe(48000)
        ->and($wallet->json('data.stats.collected_total'))->toBe(48000)
        ->and($wallet->json('data.stats.invoices_delivered'))->toBe(1)
        ->and($wallet->json('data.stats.receivables'))->toBe(0);
});

it('replays the same client_op_id and 409s a reused receipt_no', function () {
    $world = repWalletWorld();
    switchAppUser($world['rep']);
    $receiptNo = $this->postJson('/api/v1/app/receipts/reserve')->json('data.receipt_no');
    $body = [
        'receipt_no' => $receiptNo,
        'retailer_id' => $world['retailer_id'],
        'invoice_no' => $world['invoice']['no'],
        'amount' => 48000,
        'paid_at' => '2026-03-01T12:08:00+03:00',
        'client_op_id' => 'op_rep_pay_replay',
    ];

    $first = $this->postJson('/api/v1/app/rep/payments', $body);
    CatalogAssert::ok($first);
    $second = $this->postJson('/api/v1/app/rep/payments', $body);
    CatalogAssert::ok($second);
    expect($second->json('data.payment.id'))->toBe($first->json('data.payment.id'));

    $other = $this->postJson('/api/v1/app/receipts/reserve')->json('data.receipt_no');
    CatalogAssert::error(
        $this->postJson('/api/v1/app/rep/payments', [
            'receipt_no' => $receiptNo,
            'retailer_id' => $world['retailer_id'],
            'invoice_no' => $world['invoice']['no'],
            'amount' => 1000,
            'paid_at' => '2026-03-01T12:09:00+03:00',
            'client_op_id' => 'op_rep_pay_other',
        ]),
        409,
        'duplicate_receipt_no',
    );
    expect($other)->toStartWith('RCPT-');
});

it('404s a foreign invoice and a reservation belonging to another rep', function () {
    $own = repWalletWorld();
    $foreign = repWalletWorld();
    switchAppUser($own['rep']);
    $ownReceipt = $this->postJson('/api/v1/app/receipts/reserve')->json('data.receipt_no');

    CatalogAssert::error(
        $this->postJson('/api/v1/app/rep/payments', [
            'receipt_no' => $ownReceipt,
            'retailer_id' => $foreign['retailer_id'],
            'invoice_no' => $foreign['invoice']['no'],
            'amount' => 48000,
            'paid_at' => '2026-03-01T12:08:00+03:00',
            'client_op_id' => 'op_foreign_invoice',
        ]),
        404,
        'not_found',
    );

    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shopB = AppSurface::retailer($refs);
    $repA = AppSurface::rep($channel, $refs);
    $repB = AppSurface::rep($channel, $refs);
    $invoiceB = app(IssuesInvoice::class)->issue(
        random_int(1, 2_000_000_000),
        $channel->id,
        AppSurface::retailerId($shopB),
        48000,
        $repB->id,
    );
    switchAppUser($repA);
    $aReceipt = $this->postJson('/api/v1/app/receipts/reserve')->json('data.receipt_no');
    switchAppUser($repB);
    CatalogAssert::error(
        $this->postJson('/api/v1/app/rep/payments', [
            'receipt_no' => $aReceipt,
            'retailer_id' => AppSurface::retailerId($shopB),
            'invoice_no' => $invoiceB['no'],
            'amount' => 48000,
            'paid_at' => '2026-03-01T12:08:00+03:00',
            'client_op_id' => 'op_foreign_receipt',
        ]),
        404,
        'not_found',
    );
})->group('tenancy');

it('blocks collection that would exceed the cash hold cap until a withdrawal', function () {
    $world = repWalletWorld();
    $manager = ChannelUser::factory()->forChannel($world['channel'])->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');
    $this->putJson('/api/v1/channel/reps/'.$world['rep']->id.'/discount-cap', [
        'max_discount_percent' => 0,
        'max_cash_hold' => 50000,
    ])->assertOk();

    switchAppUser($world['rep']);
    $firstReceipt = $this->postJson('/api/v1/app/receipts/reserve')->json('data.receipt_no');
    CatalogAssert::ok($this->postJson('/api/v1/app/rep/payments', [
        'receipt_no' => $firstReceipt,
        'retailer_id' => $world['retailer_id'],
        'invoice_no' => $world['invoice']['no'],
        'amount' => 48000,
        'paid_at' => '2026-03-01T12:08:00+03:00',
        'client_op_id' => 'op_under_cap',
    ]));

    $secondInvoice = app(IssuesInvoice::class)->issue(
        random_int(1, 2_000_000_000),
        $world['channel']->id,
        $world['retailer_id'],
        20000,
        $world['rep']->id,
    );
    $secondReceipt = $this->postJson('/api/v1/app/receipts/reserve')->json('data.receipt_no');
    CatalogAssert::error(
        $this->postJson('/api/v1/app/rep/payments', [
            'receipt_no' => $secondReceipt,
            'retailer_id' => $world['retailer_id'],
            'invoice_no' => $secondInvoice['no'],
            'amount' => 20000,
            'paid_at' => '2026-03-01T12:10:00+03:00',
            'client_op_id' => 'op_over_cap',
        ]),
        403,
        'cash_cap_exceeded',
    );

    CatalogAssert::ok($this->postJson('/api/v1/app/rep/wallet/withdrawals', [
        'amount' => 48000,
        'operation_no' => 'OP-CAP-1',
        'operated_at' => '2026-03-01T16:00:00+03:00',
    ]));
    CatalogAssert::ok($this->postJson('/api/v1/app/rep/payments', [
        'receipt_no' => $secondReceipt,
        'retailer_id' => $world['retailer_id'],
        'invoice_no' => $secondInvoice['no'],
        'amount' => 20000,
        'paid_at' => '2026-03-01T16:10:00+03:00',
        'client_op_id' => 'op_after_withdraw',
    ]));
});

it('records a cash handover once per operation_no and lists it', function () {
    $world = repWalletWorld();
    switchAppUser($world['rep']);
    $receiptNo = $this->postJson('/api/v1/app/receipts/reserve')->json('data.receipt_no');
    $this->postJson('/api/v1/app/rep/payments', [
        'receipt_no' => $receiptNo,
        'retailer_id' => $world['retailer_id'],
        'invoice_no' => $world['invoice']['no'],
        'amount' => 48000,
        'paid_at' => '2026-03-01T12:08:00+03:00',
        'client_op_id' => 'op_for_withdraw',
    ])->assertOk();

    $body = [
        'amount' => 30000,
        'operation_no' => 'OP-7781',
        'operated_at' => '2026-03-01T16:00:00+03:00',
    ];
    $first = $this->postJson('/api/v1/app/rep/wallet/withdrawals', $body);
    CatalogAssert::ok($first);
    expect($first->json('data.remaining_balance'))->toBe(18000);

    $replay = $this->postJson('/api/v1/app/rep/wallet/withdrawals', $body);
    CatalogAssert::ok($replay);
    expect($replay->json('data.remaining_balance'))->toBe(18000);

    $history = $this->getJson('/api/v1/app/rep/wallet/withdrawals?date_from=2026-03-01&date_to=2026-03-01');
    CatalogAssert::ok($history);
    expect($history->json('data.rows'))->toHaveCount(1)
        ->and($history->json('data.total'))->toBe(30000)
        ->and($history->json('data.rows.0.operation_no'))->toBe('OP-7781');
});

it('groups open shop receivables for the collecting rep', function () {
    $world = repWalletWorld();
    switchAppUser($world['rep']);

    $list = $this->getJson('/api/v1/app/rep/receivables');
    CatalogAssert::ok($list);
    expect($list->json('data.by_shop'))->toHaveCount(1)
        ->and($list->json('data.by_shop.0.retailer_id'))->toBe($world['retailer_id'])
        ->and($list->json('data.by_shop.0.invoices.0.no'))->toBe($world['invoice']['no'])
        ->and($list->json('data.by_shop.0.invoices.0.remaining'))->toBe(48000)
        ->and($list->json('data.by_shop.0.total'))->toBe(48000);
});

it('lets the accountant settle the same wallet the rep collected into', function () {
    $world = repWalletWorld();
    switchAppUser($world['rep']);
    $receiptNo = $this->postJson('/api/v1/app/receipts/reserve')->json('data.receipt_no');
    $this->postJson('/api/v1/app/rep/payments', [
        'receipt_no' => $receiptNo,
        'retailer_id' => $world['retailer_id'],
        'invoice_no' => $world['invoice']['no'],
        'amount' => 48000,
        'paid_at' => '2026-03-01T12:08:00+03:00',
        'client_op_id' => 'op_for_office_settle',
    ])->assertOk();

    $manager = ChannelUser::factory()->forChannel($world['channel'])->create();
    $manager->assignRole('channel_manager');
    app('auth')->forgetGuards();
    Sanctum::actingAs($manager, ['*'], 'channel');

    $settled = $this->postJson('/api/v1/channel/reps/'.$world['rep']->id.'/settle', [
        'amount' => 20000,
        'operation_no' => 'OP-OFFICE-1',
    ]);
    CatalogAssert::ok($settled);
    expect($settled->json('data.new_balance'))->toBe(28000);

    $balance = Tenant::as($world['channel']->id, fn () => (int) WalletTransaction::query()
        ->where('rep_id', $world['rep']->id)
        ->where('type', 'collected')
        ->sum('amount')
        - (int) WalletTransaction::query()
            ->where('rep_id', $world['rep']->id)
            ->where('type', 'settled')
            ->sum('amount'));
    expect($balance)->toBe(28000)
        ->and(Tenant::as($world['channel']->id, fn () => (int) Invoice::query()->find($world['invoice']['id'])?->paid_total))->toBe(48000);
});
