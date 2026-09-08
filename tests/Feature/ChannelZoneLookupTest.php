<?php

declare(strict_types=1);

/**
 * `ChannelZoneLookup` reads across channels and cannot write.
 *
 * Two models share `channel_zone`, which is a back door onto a scoped table unless the
 * boundary between them is enforced rather than described. Reference's `ChannelZone` is
 * the channel's write path and carries `BelongsToChannel`; this one is an unscoped read
 * for questions asked from outside a tenant, and its `$fillable` is empty so the write
 * half is actually shut.
 *
 * Without the tests below the arrangement rests on a docblock, and a later `$fillable`
 * that someone adds "to make a test easier" would reopen an unscoped write path onto a
 * scoped table with nothing failing.
 */

use Illuminate\Database\Eloquent\MassAssignmentException;
use Modules\Core\Support\Tenant;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\ChannelZoneLookup;
use Modules\Tenancy\Domain\Models\SupplyChannel;

afterEach(fn () => Tenant::forget());

it('refuses to create a coverage row', function () {
    $channel = SupplyChannel::factory()->create();
    $zone = Zone::factory()->create();

    expect(fn () => ChannelZoneLookup::query()->create([
        'supply_channel_id' => $channel->id,
        'zone_id' => $zone->id,
    ]))->toThrow(MassAssignmentException::class);

    expect(ChannelZoneLookup::query()->count())->toBe(0);
})->group('tenancy');

it('refuses to fill an existing row', function () {
    $channelA = SupplyChannel::factory()->create();
    $channelB = SupplyChannel::factory()->create();
    $zone = Zone::factory()->create();

    Tenant::as($channelA->id, fn () => ChannelZone::query()->create(['zone_id' => $zone->id]));

    $row = ChannelZoneLookup::query()->firstOrFail();

    // The write that would matter most: repointing a coverage row at another channel.
    expect(fn () => $row->fill(['supply_channel_id' => $channelB->id]))
        ->toThrow(MassAssignmentException::class);

    expect($row->refresh()->supply_channel_id)->toBe($channelA->id);
})->group('tenancy');

it('reads every channel, which is why it is exempt from the scope', function () {
    $channelA = SupplyChannel::factory()->create();
    $channelB = SupplyChannel::factory()->create();
    $zone = Zone::factory()->create();

    Tenant::as($channelA->id, fn () => ChannelZone::query()->create(['zone_id' => $zone->id]));
    Tenant::as($channelB->id, fn () => ChannelZone::query()->create(['zone_id' => $zone->id]));

    // No tenant is set at all here. A scoped model would throw
    // MissingChannelScopeException; this one answers, because "which channels cover this
    // zone" is asked during registration, before the caller belongs to a channel.
    $ids = ChannelZoneLookup::query()->where('zone_id', $zone->id)
        ->pluck('supply_channel_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

    expect($ids)->toBe([$channelA->id, $channelB->id]);
})->group('tenancy');

it('writes only through the scoped model, which stamps the tenant', function () {
    $channel = SupplyChannel::factory()->create();
    $zone = Zone::factory()->create();

    // `supply_channel_id` is not in ChannelZone's $fillable either — the scope supplies
    // it from the current tenant, so a row cannot be created for someone else's channel.
    Tenant::as($channel->id, fn () => ChannelZone::query()->create(['zone_id' => $zone->id]));

    $row = ChannelZoneLookup::query()->firstOrFail();

    expect((int) $row->supply_channel_id)->toBe($channel->id)
        ->and((int) $row->zone_id)->toBe($zone->id);
})->group('tenancy');
