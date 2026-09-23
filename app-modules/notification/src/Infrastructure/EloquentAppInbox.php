<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure;

use Modules\Core\Contracts\AppInbox;
use Modules\Notification\Domain\Models\AppNotification;

final class EloquentAppInbox implements AppInbox
{
    public function unreadCount(int $appUserId, string $kind): int
    {
        return AppNotification::query()
            ->where('recipient_kind', $kind)
            ->where('recipient_id', $appUserId)
            ->whereNull('dismissed_at')
            ->whereNull('read_at')
            ->count();
    }
}
