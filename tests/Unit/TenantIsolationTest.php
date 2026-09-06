<?php

/**
 * `ChannelScope` measured against a throwaway table, deliberately outside tests/Feature.
 *
 * This file builds its own fixture table rather than borrowing a real one, which is the
 * right shape for the thing under test — but under `tests/Feature` it was quietly
 * corrupting the whole suite. `tests/Pest.php` applies `RefreshDatabase` to Feature only,
 * so every test there runs inside a wrapping transaction, and **MySQL commits implicitly
 * on any DDL**. The `Schema::create` below ended that transaction instead of being rolled
 * back with it, and the suite then failed in unrelated files with missing or duplicated
 * tables — 10 to 19 errors on a different random set each run, never an assertion.
 *
 * Adding `Schema::dropIfExists` to `afterEach` does not fix that: the drop is DDL too,
 * a second implicit commit. The transaction has to not exist, which is what `tests/Unit`
 * gives. Nothing here needs Feature anyway — no HTTP request, no seeded rows, no migrated
 * table. Only `tenant_fixtures`, `Tenant` and `BelongsToChannel`.
 *
 * The price of leaving the transaction behind is that cleanup is now this file's job:
 * it drops the table before creating it and again afterwards, so a run that dies midway
 * cannot poison the next one. `tests/Architecture/TestSuiteDdlTest.php` keeps the DDL
 * from drifting back into Feature.
 */

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Domain\Exceptions\MissingChannelScopeException;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Core\Support\Tenant;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $label
 */
class ChannelScopedFixture extends Model
{
    use BelongsToChannel;

    protected $table = 'tenant_fixtures';

    public $timestamps = false;

    protected $guarded = [];
}

beforeEach(function () {
    // No transaction wraps a Unit test, so nothing rolls this table back. Drop first in
    // case a previous run was interrupted between its create and its afterEach.
    Schema::dropIfExists('tenant_fixtures');

    Schema::create('tenant_fixtures', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('supply_channel_id')->index();
        $table->string('label');
    });

    Tenant::forget();

    ChannelScopedFixture::withoutGlobalScope('channel')->insert([
        ['supply_channel_id' => 1, 'label' => 'ch1-a'],
        ['supply_channel_id' => 1, 'label' => 'ch1-b'],
        ['supply_channel_id' => 1, 'label' => 'ch1-c'],
        ['supply_channel_id' => 2, 'label' => 'ch2-a'],
        ['supply_channel_id' => 2, 'label' => 'ch2-b'],
    ]);
});

afterEach(function () {
    Tenant::forget();
    Schema::dropIfExists('tenant_fixtures');
});

it('returns only the current channel rows', function () {
    Tenant::set(1);
    expect(ChannelScopedFixture::count())->toBe(3);

    Tenant::set(2);
    expect(ChannelScopedFixture::count())->toBe(2);
})->group('tenancy');

it('cannot reach another channel row by id', function () {
    $foreignId = ChannelScopedFixture::withoutGlobalScope('channel')
        ->where('supply_channel_id', 2)
        ->value('id');

    Tenant::set(1);

    expect(ChannelScopedFixture::find($foreignId))->toBeNull();
})->group('tenancy');

it('stamps the current channel on create', function () {
    Tenant::set(2);

    $row = ChannelScopedFixture::create(['label' => 'new']);

    expect($row->supply_channel_id)->toBe(2);
})->group('tenancy');

it('does not leak through aggregates', function () {
    Tenant::set(1);

    expect(ChannelScopedFixture::pluck('label')->all())
        ->each->toStartWith('ch1-');
})->group('tenancy');

it('throws when a channel-scoped query runs with no tenant', function () {
    ChannelScopedFixture::count();
})->throws(MissingChannelScopeException::class)->group('tenancy');

it('sees everything inside Tenant::withoutScope', function () {
    expect(Tenant::withoutScope(fn () => ChannelScopedFixture::count()))->toBe(5);
})->group('tenancy');

it('restores the previous channel after Tenant::as', function () {
    Tenant::set(1);

    $count = Tenant::as(2, fn () => ChannelScopedFixture::count());

    expect($count)->toBe(2)
        ->and(Tenant::currentId())->toBe(1);
})->group('tenancy');
