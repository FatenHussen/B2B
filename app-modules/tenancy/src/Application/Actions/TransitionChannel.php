<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Modules\Tenancy\Application\Services\ChannelLifecycle;
use Modules\Tenancy\Domain\ChannelStateMachine;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Spatie\Permission\Exceptions\UnauthorizedException;

/**
 * EP-AD-054 — move a channel to a new status on behalf of a platform user.
 *
 * The route is gated on `ad.channels.suspend`. The catalog adds: "Permission is
 * ad.channels.suspend / ad.channels.archive depending on target", and a route
 * middleware cannot read the target, so the second half of that sentence lives here.
 * DOC-08 defines `ad.channels.suspend` as "إيقاف قناة أو رفع الإيقاف" — both directions
 * between active and suspended — and `ad.channels.archive` as "أرشفة قناة", a separate,
 * more severe grant. Holding the first does not reach `archived`.
 */
final class TransitionChannel
{
    public function __construct(
        private readonly ChannelLifecycle $lifecycle,
        private readonly ChannelStateMachine $machine,
    ) {}

    /**
     * @param  object  $actor  the authenticated platform user
     * @return array{status: string, allowed_next: list<string>}
     */
    public function __invoke(SupplyChannel $channel, ChannelStatus $to, object $actor, string $reason): array
    {
        if ($to === ChannelStatus::Archived && ! $this->mayArchive($actor)) {
            // The same exception the `permission:` middleware throws, so the 403 carries
            // `error.permission` exactly as a route-level refusal would.
            throw UnauthorizedException::forPermissions(['ad.channels.archive']);
        }

        $channel = $this->lifecycle->transition($channel, $to, $actor, $reason);

        return [
            'status' => $channel->status->value,
            'allowed_next' => array_map(
                static fn (ChannelStatus $status): string => $status->value,
                $this->machine->allowedNext($channel->status),
            ),
        ];
    }

    private function mayArchive(object $actor): bool
    {
        return method_exists($actor, 'can') && $actor->can('ad.channels.archive');
    }
}
