<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Listeners;

use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Events\SubOrderAssigned;
use Modules\Notification\Application\Support\InboxWriter;

final class NotifyOnSubOrderAssigned
{
    public function __construct(
        private readonly InboxWriter $inbox,
        private readonly SubOrderLifecycle $orders,
    ) {}

    public function handle(SubOrderAssigned $event): void
    {
        $header = $this->orders->header($event->subOrderId);
        $shop = is_string($header['shop_name'] ?? null) ? $header['shop_name'] : '';
        $no = is_string($header['sub_order_no'] ?? null) ? $header['sub_order_no'] : (string) $event->subOrderId;

        $this->inbox->write(
            'rep',
            $event->repUserId,
            $event->channelId,
            'order',
            'طلب جديد بانتظار القبول',
            $shop !== '' ? "{$shop} — {$no}" : $no,
            ['type' => 'assignment', 'target' => $event->subOrderId],
        );
    }
}
