<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Listeners;

use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Events\SubOrderConfirmed;
use Modules\Notification\Application\Support\InboxWriter;

final class NotifyOnSubOrderConfirmed
{
    public function __construct(
        private readonly InboxWriter $inbox,
        private readonly SubOrderLifecycle $orders,
        private readonly RetailerDirectory $retailers,
    ) {}

    public function handle(SubOrderConfirmed $event): void
    {
        $header = $this->orders->header($event->subOrderId);
        if ($header === null) {
            return;
        }

        $userId = $this->retailers->appUserId((int) $header['retailer_id']);
        if ($userId === null) {
            return;
        }

        $no = $header['sub_order_no'];

        $this->inbox->write(
            'retailer',
            $userId,
            $event->channelId,
            'order',
            'تم تأكيد طلبك',
            "الطلب {$no} قيد التجهيز",
            ['type' => 'order', 'target' => $event->subOrderId],
        );
    }
}
