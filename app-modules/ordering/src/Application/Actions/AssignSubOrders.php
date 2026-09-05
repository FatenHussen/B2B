<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\HandoverGuard;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Core\Domain\Events\SubOrderAssigned;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Ordering\Domain\Enums\AssignmentStatus;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderAssignment;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\SubOrderStateMachine;

final class AssignSubOrders
{
    public function __construct(
        private readonly SubOrderStateMachine $machine,
        private readonly RepDutyLookup $duty,
        private readonly RepDirectory $reps,
        private readonly HandoverGuard $handover,
    ) {}

    /**
     * @param  array{sub_order_ids: list<int>, rep_id: int, mode?: string}  $data
     * @return array{assigned: list<int>}
     */
    public function __invoke(object $actor, array $data, bool $reassign = false, ?int $singleId = null): array
    {
        $repId = (int) $data['rep_id'];
        $channelId = (int) Tenant::currentId();
        if (! $this->reps->belongsToChannel($repId, $channelId)) {
            throw new DomainException(__('ordering.rep_off_coverage'), 'validation_failed', 422);
        }
        if (! $this->duty->isOnDuty($repId)) {
            throw new DomainException(__('ordering.rep_off_duty'), 'validation_failed', 422);
        }

        $repZones = $this->duty->zoneIds($repId);
        $ids = $singleId !== null ? [$singleId] : array_map('intval', $data['sub_order_ids'] ?? []);
        $assigned = [];

        foreach ($ids as $id) {
            $sub = SubOrder::query()->find($id);
            if ($sub === null) {
                continue;
            }
            $this->machine->assert($sub->status, $reassign ? 'reassign' : 'assign');
            if ($reassign && $this->handover->isConfirmedForSubOrder((int) $sub->id)) {
                throw new DomainException(__('ordering.illegal_transition'), 'illegal_transition', 409);
            }
            if ($sub->zone_id && $repZones !== [] && ! in_array((int) $sub->zone_id, $repZones, true)) {
                throw new DomainException(__('ordering.rep_off_coverage'), 'validation_failed', 422);
            }

            $sub->status = SubOrderStatus::Assigned;
            $sub->rep_id = $repId;
            $sub->save();

            SubOrderAssignment::query()->create([
                'sub_order_id' => $sub->id,
                'rep_id' => $repId,
                'status' => AssignmentStatus::Pending,
            ]);
            SubOrderEvent::query()->create([
                'sub_order_id' => $sub->id,
                'stage' => SubOrderStatus::Assigned->value,
                'at' => now(),
                'actor_type' => $actor::class,
                'actor_id' => $actor->getAuthIdentifier(),
            ]);
            event(new SubOrderAssigned((int) $sub->id, $repId, (int) $sub->channel_id));
            $assigned[] = $id;
        }

        return $reassign
            ? ['status' => SubOrderStatus::Assigned->value]
            : ['assigned' => $assigned];
    }
}
