<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Events\ChannelStatusChanged;
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
 *
 * It is also the only place `ChannelStatusChanged` is dispatched. Because it is the only
 * writer, dispatching from here is the guarantee that every transition is announced —
 * deferring the event would have meant either a second place that knows about it, or
 * an announcement with no such guarantee.
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

        $actorId = method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null;
        // One instant for the row and the event, so a listener reconciling against
        // channel_events finds the row this event describes.
        $at = now()->toImmutable();

        DB::transaction(function () use ($channel, $from, $to, $actor, $actorId, $reason, $at): void {
            // Direct assignment, not update(): `status` is guarded, and mass assignment
            // would drop it silently and answer 200 having changed nothing (BE-R02).
            $channel->status = $to;
            $channel->save();

            ChannelEvent::query()->create([
                'channel_id' => $channel->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $actor::class,
                'actor_id' => $actorId,
                'reason' => $reason,
                'at' => $at,
            ]);
        });

        // After the transaction, not inside it: a synchronous listener must never act on
        // a transition that is about to roll back.
        event(new ChannelStatusChanged(
            channelId: (int) $channel->id,
            fromStatus: $from->value,
            toStatus: $to->value,
            actorType: $actor::class,
            actorId: $actorId,
            reason: $reason,
            at: $at,
        ));

        return $channel;
    }
}
