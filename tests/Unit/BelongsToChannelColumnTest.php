<?php

declare(strict_types=1);

/**
 * `BelongsToChannel` isolates on either column name.
 *
 * Kept rather than deleted after it proved the point, because it is the only place the
 * two-name arrangement is demonstrated rather than described. Twelve models use
 * `channel_id` and sixteen use `supply_channel_id`; the trait defaults to the second and
 * takes the first through `$channelColumn`. If someone later "tidies" that property away,
 * this file fails immediately and says which half broke.
 *
 * In `tests/Unit` deliberately. It creates its own tables, and DDL inside a
 * `tests/Feature` test implicitly commits RefreshDatabase's transaction and corrupts
 * unrelated files — see `tests/Architecture/TestSuiteDdlTest.php`, which forbids exactly
 * that. No transaction wraps a Unit test, so cleanup is explicit in both directions.
 */

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Domain\Exceptions\MissingChannelScopeException;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Core\Support\Tenant;

/** The sixteen-model default: declares nothing. */
class DefaultColumnFixture extends Model
{
    use BelongsToChannel;

    protected $table = 'scope_default_fixtures';

    public $timestamps = false;

    protected $guarded = [];
}

/** The other twelve: declares the second spelling. */
class ChannelIdColumnFixture extends Model
{
    use BelongsToChannel;

    protected $table = 'scope_channel_id_fixtures';

    public $timestamps = false;

    protected $guarded = [];

    protected string $channelColumn = 'channel_id';
}

beforeEach(function () {
    Schema::dropIfExists('scope_default_fixtures');
    Schema::dropIfExists('scope_channel_id_fixtures');

    Schema::create('scope_default_fixtures', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('supply_channel_id')->index();
        $table->string('label');
    });

    Schema::create('scope_channel_id_fixtures', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('channel_id')->index();
        $table->string('label');
    });

    Tenant::forget();

    DefaultColumnFixture::withoutGlobalScope('channel')->insert([
        ['supply_channel_id' => 1, 'label' => 'a-1'],
        ['supply_channel_id' => 1, 'label' => 'a-2'],
        ['supply_channel_id' => 2, 'label' => 'b-1'],
    ]);

    ChannelIdColumnFixture::withoutGlobalScope('channel')->insert([
        ['channel_id' => 1, 'label' => 'a-1'],
        ['channel_id' => 1, 'label' => 'a-2'],
        ['channel_id' => 2, 'label' => 'b-1'],
    ]);
});

afterEach(function () {
    Tenant::forget();
    Schema::dropIfExists('scope_default_fixtures');
    Schema::dropIfExists('scope_channel_id_fixtures');
});

it('defaults to supply_channel_id when the model declares nothing', function () {
    expect((new DefaultColumnFixture)->channelColumn())->toBe('supply_channel_id');
})->group('tenancy');

it('takes channel_id from the declared property', function () {
    expect((new ChannelIdColumnFixture)->channelColumn())->toBe('channel_id');
})->group('tenancy');

it('isolates rows on supply_channel_id', function () {
    Tenant::set(1);
    expect(DefaultColumnFixture::count())->toBe(2);

    Tenant::set(2);
    expect(DefaultColumnFixture::count())->toBe(1);
})->group('tenancy');

it('isolates rows on channel_id', function () {
    // The half that was impossible before `$channelColumn` existed: the same query would
    // have asked for `scope_channel_id_fixtures.supply_channel_id` and thrown.
    Tenant::set(1);
    expect(ChannelIdColumnFixture::count())->toBe(2);

    Tenant::set(2);
    expect(ChannelIdColumnFixture::count())->toBe(1);
})->group('tenancy');

it('cannot reach another channel row by id, on either column', function () {
    $foreignDefault = DefaultColumnFixture::withoutGlobalScope('channel')
        ->where('supply_channel_id', 2)->value('id');
    $foreignChannelId = ChannelIdColumnFixture::withoutGlobalScope('channel')
        ->where('channel_id', 2)->value('id');

    Tenant::set(1);

    expect(DefaultColumnFixture::find($foreignDefault))->toBeNull()
        ->and(ChannelIdColumnFixture::find($foreignChannelId))->toBeNull();
})->group('tenancy');

it('stamps the current channel on create, on either column', function () {
    Tenant::set(2);

    $default = DefaultColumnFixture::create(['label' => 'new']);
    $other = ChannelIdColumnFixture::create(['label' => 'new']);

    expect($default->supply_channel_id)->toBe(2)
        ->and($other->channel_id)->toBe(2);
})->group('tenancy');

it('throws for either column when no tenant is set', function () {
    expect(fn () => DefaultColumnFixture::count())
        ->toThrow(MissingChannelScopeException::class)
        ->and(fn () => ChannelIdColumnFixture::count())
        ->toThrow(MissingChannelScopeException::class);
})->group('tenancy');

it('sees every channel inside Tenant::withoutScope, on either column', function () {
    expect(Tenant::withoutScope(fn () => DefaultColumnFixture::count()))->toBe(3)
        ->and(Tenant::withoutScope(fn () => ChannelIdColumnFixture::count()))->toBe(3);
})->group('tenancy');
