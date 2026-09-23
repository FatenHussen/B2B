<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Support;

use Modules\Notification\Domain\Models\AppNotification;

final class InboxWriter
{
    /**
     * @param  array{type: string, target: int|string|null}  $action
     */
    public function write(
        string $kind,
        int $recipientId,
        ?int $channelId,
        string $icon,
        string $title,
        string $body,
        array $action,
    ): void {
        AppNotification::query()->create([
            'channel_id' => $channelId,
            'recipient_kind' => $kind,
            'recipient_id' => $recipientId,
            'icon' => $icon,
            'title' => $title,
            'body' => $body,
            'action' => $action,
            'sent_at' => now(),
        ]);
    }
}
