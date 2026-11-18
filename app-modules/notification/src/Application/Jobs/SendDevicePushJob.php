<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notification\Application\Support\PushDispatcher;
use RuntimeException;

final class SendDevicePushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 20;

    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $recipientId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data,
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): int
    {
        return 30;
    }

    public function handle(PushDispatcher $dispatcher): void
    {
        $result = $dispatcher->toRecipient(
            $this->kind,
            $this->recipientId,
            $this->title,
            $this->body,
            $this->data,
        );

        if (! $result['ok'] && $result['reason'] === 'fcm_unreachable') {
            throw new RuntimeException('FCM was unreachable.');
        }
    }
}
