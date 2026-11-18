<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Support;

use Modules\Core\Contracts\DevicePush;
use Modules\Notification\Application\Jobs\SendDevicePushJob;
use Modules\Notification\Domain\Models\PushToken;

final class DevicePushQueue
{
    public function __construct(private readonly DevicePush $push) {}

    public function configured(): bool
    {
        return $this->push->configured();
    }

    /**
     * @param  array{type?: string, target?: int|string|null}  $action
     */
    public function send(string $kind, int $recipientId, string $title, string $body, array $action): void
    {
        if (! $this->push->configured()) {
            return;
        }

        $registered = PushToken::query()
            ->where('recipient_kind', $kind)
            ->where('recipient_id', $recipientId)
            ->exists();
        if (! $registered) {
            return;
        }

        SendDevicePushJob::dispatch(
            $kind,
            $recipientId,
            $title,
            $body,
            PushDispatcher::dataFromAction($action),
        );
    }
}
