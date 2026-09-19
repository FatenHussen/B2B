<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Core\Contracts\HandoverGuard;
use Modules\Core\Contracts\RepCollectedToday;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Identity\Domain\Models\AppUser;

/**
 * EP-RP-002 — morning home for the field rep. Counts only, via contracts. Never calls
 * GET /app/rep/deliveries (that list materialises delivery rows).
 */
final class ShowRepHome
{
    public function __construct(
        private readonly RepDutyLookup $duty,
        private readonly SubOrderLifecycle $orders,
        private readonly HandoverGuard $handovers,
        private readonly RepCollectedToday $collected,
    ) {}

    /**
     * @return array{
     *     greeting: array{name: string, avatar: null},
     *     server_time: string,
     *     on_duty: bool,
     *     tracking_enabled: bool,
     *     tasks: array{
     *         orders_today: int,
     *         deliveries_pending: int,
     *         collected_today: int,
     *         assignments: int,
     *         scheduled: int,
     *         warehouse_receipts: int
     *     },
     *     loyalty: null,
     *     unread_notifications: int
     * }
     */
    public function __invoke(AppUser $user): array
    {
        $id = (int) $user->id;

        return [
            'greeting' => [
                'name' => (string) $user->name,
                'avatar' => null,
            ],
            'server_time' => now()->timezone('Asia/Damascus')->toIso8601String(),
            'on_duty' => $this->duty->isOnDuty($id),
            'tracking_enabled' => $this->duty->trackingEnabled($id),
            'tasks' => [
                'orders_today' => $this->orders->countRegisteredToday($id),
                'deliveries_pending' => $this->orders->countForRep($id, ['accepted', 'on_the_way']),
                'collected_today' => $this->collected->amount($id),
                'assignments' => $this->orders->countForRep($id, ['assigned']),
                'scheduled' => $this->orders->countForRep($id, ['postponed']),
                'warehouse_receipts' => $this->handovers->pendingReceiptCount($id),
            ],
            'loyalty' => null,
            'unread_notifications' => 0,
        ];
    }
}
