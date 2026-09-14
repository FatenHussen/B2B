<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Tenancy\Domain\ChannelStateMachine;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelEvent;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * The one place a channel's status changes (rule 8).
 *
 * `ChannelStateMachine` says whether a move is allowed; this writes it, and writes the
 * event beside it in the same transaction, so there is never a status without its
 * record or a record without its status. `tests/Architecture/ChannelStatusWriterTest`
 * keeps this the only file in Tenancy that assigns a status.
 */
final class ChannelLifecycle
{
    public function __construct(private readonly ChannelStateMachine $machine) {}

    /**
     * Move `$channel` to `$to`, recording who asked and why.
     *
     * @param  object  $actor  the authenticated user; its class and id go on the event
     * @param  string  $reason  required — a transition without one is not recorded, and
     *                          a transition that is not recorded does not happen
     *
     * @throws DomainException 409 `illegal_transition`
     */
    public function transition(SupplyChannel $channel, ChannelStatus $to, object $actor, string $reason): SupplyChannel
    {
        $from = $channel->status;

        $this->machine->assert($from, $to);

        DB::transaction(function () use ($channel, $from, $to, $actor, $reason): void {
            // Direct assignment, not update(): `status` is guarded, and mass assignment
            // would drop it silently and answer 200 having changed nothing (BE-R02).
            $channel->status = $to;
            $channel->save();

            ChannelEvent::query()->create([
                'channel_id' => $channel->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $actor::class,
                'actor_id' => method_exists($actor, 'getAuthIdentifier') ? $actor->getAuthIdentifier() : null,
                'reason' => $reason,
                'at' => now(),
            ]);
        });

        return $channel;
    }
}
