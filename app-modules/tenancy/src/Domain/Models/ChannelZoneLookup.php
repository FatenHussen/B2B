<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only lookup over `channel_zone`, for questions asked from outside a tenant.
 *
 * **This is the second model on that table, deliberately.** Reference owns it through
 * `ChannelZone`, which is the channel's own write path: a channel creates and edits its
 * coverage — delivery days, delivery fee, minimum order value — inside its own tenant,
 * and carries `BelongsToChannel` so it cannot see another channel's rows.
 *
 * This one answers a different kind of question, and every caller in
 * `EloquentChannelDirectory` shows it:
 *
 *     coversZone($channelId, $zoneId)       — does *this named* channel cover the zone
 *     coversAllZones($channelId, $zoneIds)  — does *this named* channel cover them all
 *     activeIdsCoveringZone($zoneId)        — *which* channels cover this zone
 *
 * The first two name the channel as an argument rather than reading the current tenant —
 * they are asked during registration, before the caller belongs to any channel. The third
 * is cross-channel by definition. So the scope is absent on purpose, not by oversight:
 * applying `BelongsToChannel` here would mean calling `acrossChannels()` in all three
 * places, and a `acrossChannels()` that appears three times in one file stops being read
 * as an exception and starts being copied to where it does not belong.
 *
 * The boundary, then: **`ChannelZone` writes inside a tenant; this reads ids across
 * tenants.** To keep that boundary enforceable rather than merely stated, `$fillable` is
 * empty — every write goes through `ChannelZone`, and mass assignment through this model
 * throws. `tests/Architecture/ChannelScopeTest.php` records the exemption with the same
 * reason, and a test below proves the write path is actually shut.
 */
final class ChannelZoneLookup extends Model
{
    protected $table = 'channel_zone';

    public $timestamps = true;

    /**
     * Intentionally empty: this model reads, it does not write.
     *
     * Eloquent throws MassAssignmentException for any attribute passed to create() or
     * fill() while `$fillable` is empty and `$guarded` is at its default, which is the
     * behaviour wanted here — not a convention someone has to remember.
     *
     * @var list<string>
     */
    protected $fillable = [];
}
