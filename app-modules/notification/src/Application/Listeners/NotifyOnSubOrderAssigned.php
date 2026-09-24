<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Listeners;

use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Events\SubOrderAssigned;
use Modules\Notification\Application\Support\EventTemplateResolver;
use Modules\Notification\Application\Support\InboxWriter;

final class NotifyOnSubOrderAssigned
{
    public function __construct(
        private readonly InboxWriter $inbox,
        private readonly SubOrderLifecycle $orders,
        private readonly EventTemplateResolver $templates,
    ) {}

    public function handle(SubOrderAssigned $event): void
    {
        $header = $this->orders->header($event->subOrderId);
        $shop = is_string($header['shop_name'] ?? null) ? $header['shop_name'] : '';
        $no = is_string($header['sub_order_no'] ?? null) ? $header['sub_order_no'] : (string) $event->subOrderId;
        $defaultBody = $shop !== '' ? "{$shop} — {$no}" : $no;

        $tpl = $this->templates->resolve(
            $event->channelId,
            'order.assigned',
            'طلب جديد بانتظار القبول',
            $defaultBody,
            ['sub_order_no' => $no, 'shop_name' => $shop],
        );

        if (! $tpl['enabled'] || ! in_array('in_app', $tpl['channels'], true)) {
            return;
        }

        $this->inbox->write(
            'rep',
            $event->repUserId,
            $event->channelId,
            'order',
            $tpl['title'],
            $tpl['body'],
            ['type' => 'assignment', 'target' => $event->subOrderId],
        );
    }
}
