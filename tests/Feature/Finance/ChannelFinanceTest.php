<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\CreditGuard;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\InvoiceLine;
use Modules\Finance\Domain\Models\WalletTransaction;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function financeManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

function financeAccountant(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('accountant');

    return $user;
}

/**
 * @return array{retailer_id: int, rep_id: int}
 */
function financePeople(SupplyChannel $channel): array
{
    $gov = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $gov->id]);
    $activity = ActivityType::query()->create([
        'name' => 'بقالة',
        'order' => 1,
        'status' => RefStatus::Active,
    ]);

    $retailer = AppUser::factory()->retailer()->create(['status' => UserStatus::Active]);
    $profile = RetailerProfile::query()->create([
        'app_user_id' => $retailer->id,
        'shop_name' => 'محل '.$retailer->id,
        'activity_type_id' => $activity->id,
        'governorate_id' => $gov->id,
        'zone_id' => $zone->id,
        'status' => ProfileStatus::Active,
    ]);

    $rep = AppUser::factory()->rep()->create(['status' => UserStatus::Active]);
    RepProfile::query()->create([
        'app_user_id' => $rep->id,
        'channel_id' => $channel->id,
        'activity_type_id' => $activity->id,
        'status' => ProfileStatus::Active,
    ]);

    return ['retailer_id' => (int) $profile->id, 'rep_id' => (int) $rep->id];
}

/**
 * @return array{id: int, line_id: int, no: string, total: int}
 */
function financeInvoice(int $channelId, int $retailerId, int $total = 48000, ?int $repId = null): array
{
    $issued = app(IssuesInvoice::class)->issue(random_int(1, 2_000_000_000), $channelId, $retailerId, $total, $repId);
    $lineId = (int) Tenant::as($channelId, fn () => InvoiceLine::query()->where('invoice_id', $issued['id'])->value('id'));

    return ['id' => $issued['id'], 'line_id' => $lineId, 'no' => $issued['no'], 'total' => $issued['total']];
}

it('lists channel invoices and hides another channel', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $people = financePeople($own);
    $theirs = financePeople($foreign);
    $mine = financeInvoice($own->id, $people['retailer_id']);
    $hidden = financeInvoice($foreign->id, $theirs['retailer_id']);

    Sanctum::actingAs(financeManager($own), ['*'], 'channel');
    $response = $this->getJson('/api/v1/channel/invoices?filter[status]=open');
    CatalogAssert::ok($response);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($mine['id'])->not->toContain($hidden['id'])
        ->and($response->json('data.0'))->toHaveKeys(['id', 'no', 'total', 'status']);
})->group('tenancy');

it('404s a foreign invoice on void', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $theirs = financePeople($foreign);
    $hidden = financeInvoice($foreign->id, $theirs['retailer_id']);

    Sanctum::actingAs(financeManager($own), ['*'], 'channel');
    CatalogAssert::error(
        $this->postJson('/api/v1/channel/invoices/'.$hidden['id'].'/void', ['reason' => 'مكرر']),
        404,
        'not_found',
    );
})->group('tenancy');

it('does not edit a posted invoice and voids only after dual approval', function () {
    $channel = SupplyChannel::factory()->create();
    $people = financePeople($channel);
    $invoice = financeInvoice($channel->id, $people['retailer_id']);
    $requester = financeManager($channel);
    $approver = financeManager($channel);

    Sanctum::actingAs($requester, ['*'], 'channel');
    $first = $this->postJson('/api/v1/channel/invoices/'.$invoice['id'].'/void', [
        'reason' => 'إصدار مكرر بعد تعارض مزامنة',
    ]);
    CatalogAssert::ok($first);
    expect($first->json('meta.requires_dual_approval'))->toBeTrue()
        ->and(Tenant::as($channel->id, fn () => Invoice::query()->find($invoice['id'])?->status))->toBe('open');

    Sanctum::actingAs($approver, ['*'], 'channel');
    $second = $this->postJson('/api/v1/channel/invoices/'.$invoice['id'].'/void', [
        'reason' => 'إصدار مكرر بعد تعارض مزامنة',
        'approval_request_id' => $first->json('data.approval_request_id'),
        'approval_reason' => 'مراجعة',
    ]);
    CatalogAssert::ok($second);
    expect($second->json('data.status'))->toBe('void');
});

it('issues a credit note after dual approval without rewriting the invoice total', function () {
    $channel = SupplyChannel::factory()->create();
    $people = financePeople($channel);
    $invoice = financeInvoice($channel->id, $people['retailer_id']);
    $requester = financeManager($channel);
    $approver = financeManager($channel);

    Sanctum::actingAs($requester, ['*'], 'channel');
    $first = $this->postJson('/api/v1/channel/invoices/'.$invoice['id'].'/credit-note', [
        'lines' => [['line_id' => $invoice['line_id'], 'qty' => 1, 'amount' => 12000]],
        'reason' => 'فرق تسليم',
    ]);
    CatalogAssert::ok($first);

    Sanctum::actingAs($approver, ['*'], 'channel');
    $second = $this->postJson('/api/v1/channel/invoices/'.$invoice['id'].'/credit-note', [
        'lines' => [['line_id' => $invoice['line_id'], 'qty' => 1, 'amount' => 12000]],
        'reason' => 'فرق تسليم',
        'approval_request_id' => $first->json('data.approval_request_id'),
        'approval_reason' => 'مراجعة',
    ]);
    CatalogAssert::ok($second);

    $row = Tenant::as($channel->id, fn () => Invoice::query()->find($invoice['id']));
    expect($second->json('data.no'))->toStartWith('CN-')
        ->and((int) $row?->total)->toBe(48000)
        ->and((int) $row?->credited_total)->toBe(12000);
});

it('records an office payment FIFO and returns the remaining receivable', function () {
    $channel = SupplyChannel::factory()->create();
    $people = financePeople($channel);
    $first = financeInvoice($channel->id, $people['retailer_id'], 30000);
    $second = financeInvoice($channel->id, $people['retailer_id'], 20000);

    Sanctum::actingAs(financeAccountant($channel), ['*'], 'channel');
    $response = $this->postJson('/api/v1/channel/payments', [
        'retailer_id' => $people['retailer_id'],
        'amount' => 35000,
        'method' => 'cash',
        'invoice_id' => $second['id'],
    ]);
    CatalogAssert::ok($response);

    expect($response->json('data.payment_id'))->toBeInt()
        ->and($response->json('data.retailer_receivable'))->toBe(15000);
});

it('returns sod_violation when confirm and payment are held together', function () {
    $channel = SupplyChannel::factory()->create();
    $people = financePeople($channel);
    financeInvoice($channel->id, $people['retailer_id']);
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->givePermissionTo('sc.finance.payment');
    $user->givePermissionTo('sc.orders.confirm');

    Sanctum::actingAs($user, ['*'], 'channel');
    CatalogAssert::error(
        $this->postJson('/api/v1/channel/payments', [
            'retailer_id' => $people['retailer_id'],
            'amount' => 1000,
            'method' => 'cash',
        ]),
        403,
        'sod_violation',
    );
});

it('returns aging buckets as integers', function () {
    $channel = SupplyChannel::factory()->create();
    $people = financePeople($channel);
    financeInvoice($channel->id, $people['retailer_id'], 12000);

    Sanctum::actingAs(financeAccountant($channel), ['*'], 'channel');
    $response = $this->getJson('/api/v1/channel/finance/aging?group_by=zone');
    CatalogAssert::ok($response);

    expect($response->json('data.group_by'))->toBe('zone')
        ->and($response->json('data.buckets.0_30'))->toBe(12000)
        ->and($response->json('data.buckets.31_60'))->toBe(0);
});

it('settles a rep wallet down to the ledger remainder', function () {
    $channel = SupplyChannel::factory()->create();
    $people = financePeople($channel);
    Tenant::as($channel->id, function () use ($channel, $people): void {
        WalletTransaction::query()->create([
            'supply_channel_id' => $channel->id,
            'rep_id' => $people['rep_id'],
            'type' => 'collected',
            'amount' => 1_750_000,
        ]);
    });

    Sanctum::actingAs(financeManager($channel), ['*'], 'channel');
    $response = $this->postJson('/api/v1/channel/reps/'.$people['rep_id'].'/settle', [
        'amount' => 1_500_000,
        'operation_no' => 'OP-7781',
    ]);
    CatalogAssert::ok($response);

    expect($response->json('data.new_balance'))->toBe(250_000)
        ->and($response->json('data.receipt_pdf_url'))->toBeString();
});

it('404s settle for a rep of another channel', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $theirs = financePeople($foreign);

    Sanctum::actingAs(financeManager($own), ['*'], 'channel');
    CatalogAssert::error(
        $this->postJson('/api/v1/channel/reps/'.$theirs['rep_id'].'/settle', [
            'amount' => 100,
            'operation_no' => 'OP-1',
        ]),
        404,
        'not_found',
    );
})->group('tenancy');

it('blocks a credit breach when on_exceed is block', function () {
    $channel = SupplyChannel::factory()->create();
    $people = financePeople($channel);
    financeInvoice($channel->id, $people['retailer_id'], 400000);

    Sanctum::actingAs(financeManager($channel), ['*'], 'channel');
    $this->putJson('/api/v1/channel/retailers/'.$people['retailer_id'].'/credit', [
        'credit_limit' => 500000,
        'grace_days' => 0,
        'on_exceed' => 'block',
    ])->assertOk();

    $guard = app(CreditGuard::class);
    expect(fn () => $guard->assertWithinLimit($people['retailer_id'], $channel->id, 200000))
        ->toThrow(DomainException::class);
});

it('stores a retailer credit limit and 404s an unknown retailer', function () {
    $channel = SupplyChannel::factory()->create();
    $people = financePeople($channel);

    Sanctum::actingAs(financeManager($channel), ['*'], 'channel');
    $ok = $this->putJson('/api/v1/channel/retailers/'.$people['retailer_id'].'/credit', [
        'credit_limit' => 500000,
        'grace_days' => 7,
        'on_exceed' => 'block',
    ]);
    CatalogAssert::ok($ok);
    expect($ok->json('data.on_exceed'))->toBe('block');

    CatalogAssert::error(
        $this->putJson('/api/v1/channel/retailers/999999/credit', [
            'credit_limit' => 1,
            'grace_days' => 0,
            'on_exceed' => 'warn',
        ]),
        404,
        'not_found',
    );
});
