<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\SubOrderStateMachine;

final class ScheduleSubOrder
{
    public function __construct(private readonly SubOrderStateMachine $machine) {}

    /**
     * @param  array{scheduled_at: string, reason?: string}  $data
     * @return array{status: string, scheduled_at: string}
     */
    public function __invoke(object $actor, int $id, array $data): array
    {
        $sub = SubOrder::query()->find($id);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }
        $this->machine->assert($sub->status, 'schedule');
        $sub->status = SubOrderStatus::Postponed;
        $sub->scheduled_at = $data['scheduled_at'];
        $sub->save();
        SubOrderEvent::query()->create([
            'sub_order_id' => $sub->id,
            'stage' => SubOrderStatus::Postponed->value,
            'at' => now(),
            'actor_type' => $actor::class,
            'actor_id' => $actor->getAuthIdentifier(),
        ]);

        return [
            'status' => SubOrderStatus::Postponed->value,
            'scheduled_at' => $sub->scheduled_at?->timezone('Asia/Damascus')->toIso8601String(),
        ];
    }
}
