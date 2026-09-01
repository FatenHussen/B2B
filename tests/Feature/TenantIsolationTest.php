<?php

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

afterEach(fn () => Tenant::forget());

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
