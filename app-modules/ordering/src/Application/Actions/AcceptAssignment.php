<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Enums\AssignmentStatus;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderAssignment;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\SubOrderStateMachine;

final class AcceptAssignment
{
    public function __construct(private readonly SubOrderStateMachine $machine) {}

    /**
     * @return array{status: string}
     */
    public function __invoke(object $user, int $id): array
    {
        $sub = SubOrder::query()->whereKey($id)->where('rep_id', $user->getAuthIdentifier())->first();
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }
        $this->machine->assert($sub->status, 'rep_accept');
        $sub->status = SubOrderStatus::Accepted;
        $sub->save();
        SubOrderAssignment::query()
            ->where('sub_order_id', $sub->id)
            ->where('rep_id', $user->getAuthIdentifier())
            ->update(['status' => AssignmentStatus::Accepted->value]);
        SubOrderEvent::query()->create([
            'sub_order_id' => $sub->id,
            'stage' => SubOrderStatus::Accepted->value,
            'at' => now(),
            'actor_type' => $user::class,
            'actor_id' => $user->getAuthIdentifier(),
        ]);

        return ['status' => SubOrderStatus::Accepted->value];
    }
}
