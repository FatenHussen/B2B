<?php

declare(strict_types=1);

/**
 * BE-R08 — currencies, decimals, and the permission split.
 *
 * Two acceptance criteria, one test each: storage stays integer regardless of decimals,
 * and the permission split is enforced server side.
 */

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Currency;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function currencyAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

it('seeds SYP with zero decimals as the base and display currency', function () {
    currencyAdmin();

    $this->getJson('/api/v1/currencies')
        ->assertOk()
        ->assertJsonPath('data.0.iso', 'SYP')
        ->assertJsonPath('data.0.decimals', 0)
        ->assertJsonPath('data.0.is_display_currency', true)
        // `is_base` is not in the catalog's response shape and must not leak into it:
        // a client that saw both would have no way to know which one it may change.
        ->assertJsonMissingPath('data.0.is_base')
        ->assertJsonMissingPath('data.0.code');
});

it('keeps storage integer regardless of decimals', function () {
    // BE-R08 acceptance criterion 1. `decimals` is presentation only — two currencies
    // with different decimals hold their amounts the same way, as integers in the
    // smallest unit, per rule 7.
    currencyAdmin();

    $this->postJson('/api/v1/currencies', [
        'iso' => 'USD', 'name' => 'دولار', 'decimals' => 2,
    ])->assertCreated()->assertJsonPath('data.decimals', 2);

    $syp = Currency::query()->where('iso', 'SYP')->firstOrFail();
    $usd = Currency::query()->where('iso', 'USD')->firstOrFail();

    expect($syp->decimals)->toBe(0)
        ->and($usd->decimals)->toBe(2)
        // The column type is what guarantees this, so assert the column and not a cast.
        ->and(DB::getSchemaBuilder()->getColumnType('currencies', 'decimals'))
        ->toBeIn(['tinyint', 'integer']);
});

it('enforces the permission split server side', function () {
    // BE-R08 acceptance criterion 2 and requirement 2: currency management takes
    // `ad.refs.currency`, not the general refs permission. A channel manager holds no
    // platform code at all, so the gate — not the guard — is what refuses.
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    $token = $manager->createToken('currency-probe', ['*'])->plainTextToken;

    $this->postJson('/api/v1/currencies', [
        'iso' => 'EUR', 'name' => 'يورو', 'decimals' => 2,
    ], ['Authorization' => 'Bearer '.$token])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission');

    expect(Currency::query()->where('iso', 'EUR')->exists())->toBeFalse();
});

it('lets any authenticated client read currencies, because decimals renders money', function () {
    // The read is deliberately open to all four guards, like governorates and zones:
    // a retailer app cannot format a price without knowing the currency's decimals.
    Sanctum::actingAs(AppUser::factory()->retailer()->create(), ['*'], 'app');

    $this->getJson('/api/v1/currencies')->assertOk()->assertJsonPath('data.0.iso', 'SYP');
});

it('never accepts is_base through the API', function () {
    // The decision this ticket turns on. is_base is the unit every stored bigInteger is
    // denominated in under rule 7; letting a request move it would reinterpret the whole
    // ledger with no data migration. It is not in $fillable, so it is silently dropped.
    currencyAdmin();

    $this->postJson('/api/v1/currencies', [
        'iso' => 'GBP', 'name' => 'جنيه', 'decimals' => 2, 'is_base' => true,
    ])->assertCreated();

    $gbp = Currency::query()->where('iso', 'GBP')->firstOrFail();
    $syp = Currency::query()->where('iso', 'SYP')->firstOrFail();

    expect($gbp->is_base)->toBeFalse()
        ->and($syp->is_base)->toBeTrue();
});

it('moves the display currency atomically, leaving exactly one', function () {
    // BE-R09 requirement 2, enforced here because this is where the column lives.
    currencyAdmin();

    $this->postJson('/api/v1/currencies', [
        'iso' => 'USD', 'name' => 'دولار', 'decimals' => 2, 'is_display_currency' => true,
    ])->assertCreated()->assertJsonPath('data.is_display_currency', true);

    expect(Currency::query()->where('is_display_currency', true)->count())->toBe(1)
        ->and(Currency::query()->where('is_display_currency', true)->value('iso'))->toBe('USD')
        // And the base currency did not follow the display currency.
        ->and(Currency::query()->where('is_base', true)->value('iso'))->toBe('SYP');
});

it('refuses a currency update with no reason', function () {
    currencyAdmin();
    $syp = Currency::query()->where('iso', 'SYP')->firstOrFail();

    $this->putJson("/api/v1/currencies/{$syp->id}", ['name' => 'Renamed'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect($syp->refresh()->name)->not->toBe('Renamed');
});

it('disables a currency instead of deleting it', function () {
    currencyAdmin();

    $this->postJson('/api/v1/currencies', [
        'iso' => 'USD', 'name' => 'دولار', 'decimals' => 2,
    ])->assertCreated();

    $usd = Currency::query()->where('iso', 'USD')->firstOrFail();

    $this->patchJson("/api/v1/currencies/{$usd->id}/status", [
        'status' => 'disabled', 'reason' => 'عملة لم تعد تُسعَّر',
    ])->assertOk()->assertJsonPath('data.status', 'disabled');

    expect($usd->refresh()->status)->toBe(RefStatus::Disabled)
        ->and(Currency::query()->whereKey($usd->id)->exists())->toBeTrue();
});

it('has no route that deletes a currency', function () {
    currencyAdmin();
    $syp = Currency::query()->where('iso', 'SYP')->firstOrFail();

    // 405, not 404: the path serves GET and PUT, so Laravel reports the method.
    $this->deleteJson("/api/v1/currencies/{$syp->id}")->assertStatus(405);

    expect(Currency::query()->whereKey($syp->id)->exists())->toBeTrue();
});

it('rejects a duplicate iso', function () {
    currencyAdmin();

    $this->postJson('/api/v1/currencies', [
        'iso' => 'SYP', 'name' => 'مكرر', 'decimals' => 0,
    ])->assertStatus(422);

    expect(Currency::query()->where('iso', 'SYP')->count())->toBe(1);
});

it('requires decimals on create rather than defaulting them', function () {
    // The column defaults to 0 for the migration's sake. That default must not answer
    // for a new currency: USD silently declared as zero-decimal misprices every amount.
    currencyAdmin();

    $this->postJson('/api/v1/currencies', ['iso' => 'JPY', 'name' => 'ين'])
        ->assertStatus(422);

    expect(Currency::query()->where('iso', 'JPY')->exists())->toBeFalse();
});
