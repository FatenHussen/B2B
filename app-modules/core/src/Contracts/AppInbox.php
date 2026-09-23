<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface AppInbox
{
    /**
     * Unread rows for this app user. Kind is `retailer` or `rep` — the same
     * vocabulary as `AppUserKind`. Identity and Catalog bind the badge; they
     * never read the inbox table.
     */
    public function unreadCount(int $appUserId, string $kind): int;
}
