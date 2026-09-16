<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\HandoverGuard;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Domain\Events\SubOrderCancelled;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\SubOrderStateMachine;

final class CancelSubOrder
{
    public function __construct(
        private readonly SubOrderStateMachine $machine,
        private readonly StockLedger $ledger,
        private readonly HandoverGuard $handover,
        private readonly RetailerShoppingContext $shopping,
    ) {}

    /**
     * @param  array{reason: string}  $data
     * @return array{status: string}
     */
    public function __invoke(object $actor, int $id, array $data, bool $retailer = false): array
    {
        // Two callers, two isolations. From the channel route the tenant scope decides what
        // this query can see. From `/app/retailer/orders/{id}/cancel` there is no tenant —
        // and there must not be one, a retailer buys from several channels — so the only
        // line between one retailer and another is `retailer_id`. It is applied here, on
        // the query, before anything is read (BE-C12): a foreign order is simply not found.
        $query = SubOrder::query();
        if ($retailer) {
            $query->where('retailer_id', $this->shopping->for($actor)['retailer_id']);
        }
        $sub = $query->find($id);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $this->machine->assert($sub->status, $retailer ? 'retailer_cancel' : 'cancel');
        if ($this->handover->isConfirmedForSubOrder((int) $sub->id)) {
            throw new DomainException(__('ordering.illegal_transition'), 'illegal_transition', 409);
        }

        if (in_array($sub->status, [SubOrderStatus::Confirmed, SubOrderStatus::Assigned, SubOrderStatus::Accepted, SubOrderStatus::Processing], true)) {
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
        event(new SubOrderCancelled((int) $sub->id, (int) $sub->channel_id, (string) $data['reason']));

        return ['status' => SubOrderStatus::Cancelled->value];
    }
}
