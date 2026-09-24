<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Listeners;

use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Events\SubOrderConfirmed;
use Modules\Notification\Application\Support\EventTemplateResolver;
use Modules\Notification\Application\Support\InboxWriter;
use Modules\Notification\Domain\Models\NotificationDeliveryLog;

final class NotifyOnSubOrderConfirmed
{
    public function __construct(
        private readonly InboxWriter $inbox,
        private readonly SubOrderLifecycle $orders,
        private readonly RetailerDirectory $retailers,
        private readonly EventTemplateResolver $templates,
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

        $no = (string) $header['sub_order_no'];
        $tpl = $this->templates->resolve(
            $event->channelId,
            'order.confirmed',
            'تم تأكيد طلبك',
            "الطلب {$no} قيد التجهيز",
            ['sub_order_no' => $no, 'order_no' => $no],
        );

        if (! $tpl['enabled'] || $tpl['channels'] === []) {
            return;
        }

        if (in_array('in_app', $tpl['channels'], true)) {
            $this->inbox->write(
                'retailer',
                $userId,
                $event->channelId,
                'order',
                $tpl['title'],
                $tpl['body'],
                ['type' => 'order', 'target' => $event->subOrderId],
            );
        }

        NotificationDeliveryLog::query()->create([
            'supply_channel_id' => $event->channelId,
            'notification_id' => null,
            'recipient' => $userId,
            'template' => 'order.confirmed',
            'status' => in_array('in_app', $tpl['channels'], true) ? 'sent' : 'skipped',
            'failure_reason' => in_array('push', $tpl['channels'], true) || in_array('whatsapp', $tpl['channels'], true)
                ? 'push_whatsapp_provider_not_configured'
                : null,
            'at' => now(),
        ]);
    }
}
