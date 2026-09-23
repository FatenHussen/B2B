<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Listeners;

use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Events\DeliveryCompleted;
use Modules\Notification\Application\Support\InboxWriter;

final class NotifyOnDeliveryCompleted
{
    public function __construct(
        private readonly InboxWriter $inbox,
        private readonly SubOrderLifecycle $orders,
        private readonly RetailerDirectory $retailers,
    ) {}

    public function handle(DeliveryCompleted $event): void
    {
        $header = $this->orders->header($event->subOrderId);
        if ($header === null) {
            return;
        }

        $userId = $this->retailers->appUserId((int) $header['retailer_id']);
        if ($userId === null) {
            return;
        }

        $this->inbox->write(
            'retailer',
            $userId,
            (int) $header['channel_id'],
            'delivery',
            'تم تسليم طلبك',
            "الطلب {$header['sub_order_no']} — فاتورة {$event->invoiceNo}",
            ['type' => 'order', 'target' => $event->subOrderId],
        );
    }
}
