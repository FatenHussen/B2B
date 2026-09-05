<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Domain\Events\SubOrderRejected;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\SubOrderStateMachine;

final class RejectSubOrder
{
    public function __construct(private readonly SubOrderStateMachine $machine) {}

    /**
     * @param  array{reason: string}  $data
     * @return array{status: string}
     */
    public function __invoke(object $actor, int $id, array $data): array
    {
        $sub = SubOrder::query()->find($id);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }
        $this->machine->assert($sub->status, 'reject');
        $sub->status = SubOrderStatus::Rejected;
        $sub->save();
        SubOrderEvent::query()->create([
            'sub_order_id' => $sub->id,
            'stage' => SubOrderStatus::Rejected->value,
            'at' => now(),
            'actor_type' => $actor::class,
            'actor_id' => $actor->getAuthIdentifier(),
        ]);
        event(new SubOrderRejected((int) $sub->id, (int) $sub->channel_id, (string) $data['reason']));

        return ['status' => SubOrderStatus::Rejected->value];
    }
}
