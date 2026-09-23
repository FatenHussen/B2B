<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Actions;

use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Notification\Domain\Models\AppNotification;

final class MarkAllNotificationsRead
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly RepSellingContext $selling,
    ) {}

    /**
     * @return array{success: true}
     */
    public function __invoke(object $user): array
    {
        $kind = $this->shopping->isRetailer($user) ? 'retailer' : ($this->selling->isRep($user) ? 'rep' : 'rep');

        AppNotification::query()
            ->where('recipient_kind', $kind)
            ->where('recipient_id', (int) $user->getAuthIdentifier())
            ->whereNull('dismissed_at')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ['success' => true];
    }
}
