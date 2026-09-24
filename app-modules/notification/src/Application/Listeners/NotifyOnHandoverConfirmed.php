<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Listeners;

use Modules\Core\Domain\Events\HandoverConfirmedByRep;
use Modules\Notification\Application\Support\EventTemplateResolver;
use Modules\Notification\Application\Support\InboxWriter;

final class NotifyOnHandoverConfirmed
{
    public function __construct(
        private readonly InboxWriter $inbox,
        private readonly EventTemplateResolver $templates,
    ) {}

    public function handle(HandoverConfirmedByRep $event): void
    {
        // Handover may not carry channel_id on the event — use null-channel template defaults.
        $channelId = 0;
        $tpl = $this->templates->resolve(
            $channelId,
            'handover.confirmed',
            'تم تأكيد الاستلام من المستودع',
            'يمكنك بدء التسليم',
            ['handover_id' => (string) $event->handoverId],
        );

        if (! $tpl['enabled'] || ! in_array('in_app', $tpl['channels'], true)) {
            // Always deliver the operational default for reps when no channel template exists.
            $this->inbox->write(
                'rep',
                $event->repUserId,
                null,
                'warehouse',
                'تم تأكيد الاستلام من المستودع',
                'يمكنك بدء التسليم',
                ['type' => 'handover', 'target' => $event->handoverId],
            );

            return;
        }

        $this->inbox->write(
            'rep',
            $event->repUserId,
            null,
            'warehouse',
            $tpl['title'],
            $tpl['body'],
            ['type' => 'handover', 'target' => $event->handoverId],
        );
    }
}
