<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Listeners;

use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Events\DeliveryCompleted;
use Modules\Notification\Application\Support\EventTemplateResolver;
use Modules\Notification\Application\Support\InboxWriter;

final class NotifyOnDeliveryCompleted
{
    public function __construct(
        private readonly InboxWriter $inbox,
        private readonly SubOrderLifecycle $orders,
        private readonly RetailerDirectory $retailers,
        private readonly EventTemplateResolver $templates,
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

        $no = (string) $header['sub_order_no'];
        $invoice = (string) $event->invoiceNo;
        $tpl = $this->templates->resolve(
            (int) $header['channel_id'],
            'order.delivered',
            'تم تسليم طلبك',
            "الطلب {$no} — فاتورة {$invoice}",
            ['sub_order_no' => $no, 'invoice_no' => $invoice],
        );

        if (! $tpl['enabled'] || ! in_array('in_app', $tpl['channels'], true)) {
            return;
        }

        $this->inbox->write(
            'retailer',
            $userId,
            (int) $header['channel_id'],
            'delivery',
            $tpl['title'],
            $tpl['body'],
            ['type' => 'order', 'target' => $event->subOrderId],
        );
    }
}
