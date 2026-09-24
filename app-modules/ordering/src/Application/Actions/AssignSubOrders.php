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
     * @param  array{sub_order_ids?: list<int>, rep_id?: int|null, mode?: string, zone_id?: int}  $data
     * @return array{assigned: list<int>}|array{status: string}
     */
    public function __invoke(object $actor, array $data, bool $reassign = false, ?int $singleId = null): array
    {
        $mode = (string) ($data['mode'] ?? 'manual');
        $channelId = (int) Tenant::currentId();
        $ids = $singleId !== null ? [$singleId] : array_map('intval', $data['sub_order_ids'] ?? []);
        $assigned = [];

        if ($mode === 'bulk_zone') {
            $zoneId = isset($data['zone_id']) ? (int) $data['zone_id'] : null;
            if ($zoneId === null && $ids !== []) {
                $first = SubOrder::query()->find($ids[0]);
                $zoneId = $first?->zone_id ? (int) $first->zone_id : null;
            }
            if ($zoneId === null) {
                throw new DomainException(__('ordering.rep_off_coverage'), 'validation_failed', 422);
            }
            $repId = isset($data['rep_id']) ? (int) $data['rep_id'] : ($this->duty->onDutyCoveringZone($channelId, $zoneId)[0] ?? 0);
            if ($repId <= 0) {
                throw new DomainException(__('ordering.rep_off_duty'), 'validation_failed', 422);
            }
            $ids = SubOrder::query()
                ->where('zone_id', $zoneId)
                ->whereIn('status', [SubOrderStatus::Confirmed, SubOrderStatus::Postponed])
                ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return $this->assignMany($actor, $ids, $repId, $channelId, $reassign);
        }

        if ($mode === 'auto') {
            foreach ($ids as $id) {
                $sub = SubOrder::query()->find($id);
                if ($sub === null || $sub->zone_id === null) {
                    continue;
                }
                $candidates = $this->duty->onDutyCoveringZone($channelId, (int) $sub->zone_id);
                $repId = $candidates[0] ?? 0;
                if ($repId <= 0) {
                    throw new DomainException(__('ordering.rep_off_duty'), 'validation_failed', 422);
                }
                $result = $this->assignMany($actor, [$id], $repId, $channelId, $reassign);
                $assigned = array_merge($assigned, $result['assigned'] ?? []);
            }

            return $reassign
                ? ['status' => SubOrderStatus::Assigned->value]
                : ['assigned' => $assigned];
        }

        $repId = (int) ($data['rep_id'] ?? 0);
        if ($repId <= 0) {
            throw new DomainException(__('ordering.rep_off_coverage'), 'validation_failed', 422);
        }

        return $this->assignMany($actor, $ids, $repId, $channelId, $reassign);
    }

    /**
     * @param  list<int>  $ids
     * @return array{assigned: list<int>}|array{status: string}
     */
    private function assignMany(object $actor, array $ids, int $repId, int $channelId, bool $reassign): array
    {
        if (! $this->reps->belongsToChannel($repId, $channelId)) {
            throw new DomainException(__('ordering.rep_off_coverage'), 'validation_failed', 422);
        }
        if (! $this->reps->isActiveInChannel($repId, $channelId)) {
            throw new DomainException(__('ordering.rep_not_active'), 'validation_failed', 422);
        }
        if (! $this->duty->isOnDuty($repId)) {
            throw new DomainException(__('ordering.rep_off_duty'), 'validation_failed', 422);
        }

        $repZones = $this->duty->zoneIds($repId);
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
