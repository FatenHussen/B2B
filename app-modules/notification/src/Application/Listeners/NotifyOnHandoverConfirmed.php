<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Listeners;

use Modules\Core\Domain\Events\HandoverConfirmedByRep;
use Modules\Notification\Application\Support\InboxWriter;

final class NotifyOnHandoverConfirmed
{
    public function __construct(private readonly InboxWriter $inbox) {}

    public function handle(HandoverConfirmedByRep $event): void
    {
        $this->inbox->write(
            'rep',
            $event->repUserId,
            null,
            'warehouse',
            'تم تأكيد الاستلام من المستودع',
            'يمكنك بدء التسليم',
            ['type' => 'handover', 'target' => $event->handoverId],
        );
    }
}
