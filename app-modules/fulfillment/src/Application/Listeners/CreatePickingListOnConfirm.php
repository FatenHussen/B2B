<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Application\Listeners;

use Modules\Core\Contracts\CreatesPickingList;
use Modules\Core\Domain\Events\SubOrderConfirmed;

final class CreatePickingListOnConfirm
{
    public function __construct(private readonly CreatesPickingList $lists) {}

    public function handle(SubOrderConfirmed $event): void
    {
        $this->lists->create([
            'sub_order_id' => $event->subOrderId,
            'channel_id' => $event->channelId,
            'warehouse_id' => $event->warehouseId,
            'lines' => $event->lines,
        ]);
    }
}
