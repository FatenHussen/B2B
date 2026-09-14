<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

use DateTimeImmutable;

/**
 * A channel moved from one status to another (BE-T01).
 *
 * Dispatched from `ChannelLifecycle::transition()` and nowhere else. `status` is guarded
 * on `SupplyChannel` and that method is its only writer, so every transition that
 * happens is announced here, and a listener may rely on that: there is no second path a
 * status can take that this event would miss.
 *
 * No listener exists today. The freeze BR-AD-14 describes — a suspended channel takes no
 * new orders while orders in flight continue — is Ordering's act, in Ordering's layer,
 * and already holds without this event: `ChannelDirectory::activeIdsCoveringZone()` reads
 * `supply_channels.status` synchronously, so a suspended channel drops out of the
 * retailer's shopping context the moment the row changes. Rule 5: an event notifies,
 * it does not control. The first listener lands with BE-T13.
 *
 * Scalars only (rule 4): the channel is named by id, the actor by class and id, the
 * statuses by their string values — a listener in another module needs none of
 * Tenancy's models or enums to read this.
 */
final class ChannelStatusChanged
{
    public function __construct(
        public readonly int $channelId,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        public readonly string $actorType,
        public readonly ?int $actorId,
        public readonly string $reason,
        public readonly DateTimeImmutable $at,
    ) {}
}
