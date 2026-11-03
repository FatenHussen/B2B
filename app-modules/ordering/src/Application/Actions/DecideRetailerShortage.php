<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Domain\Events\SubOrderCancelled;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\ShortageAllocation;
use Modules\Ordering\Domain\Models\ShortageAllocationLine;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderEvent;

final class DecideRetailerShortage
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly StockLedger $ledger,
    ) {}

    /**
     * @param  array{decision: string}  $data
     * @return array{status: string, decision: string}
     */
    public function __invoke(object $actor, int $subOrderId, array $data): array
    {
        $decision = (string) $data['decision'];
        if (! in_array($decision, ['accept_partial', 'cancel'], true)) {
            throw new DomainException(__('ordering.validation_failed'), 'validation_failed', 422);
        }

        $retailerId = $this->shopping->for($actor)['retailer_id'];
        // acrossChannels(), per rule 10: retailer path has no tenant; owner filter is retailer_id.
        $sub = SubOrder::query()
            ->acrossChannels()
            ->where('retailer_id', $retailerId)
            ->find($subOrderId);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $pending = ShortageAllocationLine::query()
            ->where('sub_order_id', $subOrderId)
            ->where('status', 'awaiting_retailer')
            ->get();
        if ($pending->isEmpty()) {
            throw new DomainException(__('ordering.illegal_transition'), 'illegal_transition', 409);
        }

        return DB::transaction(function () use ($actor, $sub, $pending, $decision): array {
            if ($decision === 'cancel') {
                if (in_array($sub->status, [
                    SubOrderStatus::Confirmed,
                    SubOrderStatus::Assigned,
                    SubOrderStatus::Accepted,
                    SubOrderStatus::Processing,
                ], true)) {
                    $this->ledger->release((int) $sub->id, $actor);
                }
                $sub->status = SubOrderStatus::Cancelled;
                $sub->save();
                SubOrderEvent::query()->create([
                    'sub_order_id' => $sub->id,
                    'stage' => SubOrderStatus::Cancelled->value,
                    'at' => now(),
                    'actor_type' => $actor::class,
                    'actor_id' => $actor->getAuthIdentifier(),
                ]);
                foreach ($pending as $line) {
                    $line->status = 'cancelled';
                    $line->save();
                }
                event(new SubOrderCancelled((int) $sub->id, (int) $sub->channel_id, 'shortage_rejected'));

                $this->maybeCloseAllocations($pending);

                return ['status' => SubOrderStatus::Cancelled->value, 'decision' => 'cancel'];
            }

            foreach ($pending as $line) {
                $line->status = 'accepted';
                $line->save();
            }
            $this->maybeCloseAllocations($pending);

            return ['status' => $sub->status->value, 'decision' => 'accept_partial'];
        });
    }

    /**
     * @param  Collection<int, ShortageAllocationLine>  $pending
     */
    private function maybeCloseAllocations($pending): void
    {
        $allocationIds = $pending->pluck('shortage_allocation_id')->unique()->all();
        foreach ($allocationIds as $allocationId) {
            $stillWaiting = ShortageAllocationLine::query()
                ->where('shortage_allocation_id', $allocationId)
                ->where('status', 'awaiting_retailer')
                ->exists();
            if (! $stillWaiting) {
                // acrossChannels(), per rule 10: retailer shortage decision has no tenant;
                // allocation id is already scoped to this retailer's pending lines above.
                ShortageAllocation::query()
                    ->acrossChannels()
                    ->whereKey($allocationId)
                    ->update(['status' => 'resolved']);
            }
        }
    }
}
