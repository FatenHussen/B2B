<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Support;

use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\DevicePush;
use Modules\Notification\Domain\Models\PushToken;

final class PushDispatcher
{
    public function __construct(private readonly DevicePush $push) {}

    /**
     * @param  array{type?: string, target?: int|string|null}  $action
     * @return array<string, string>
     */
    public static function dataFromAction(array $action): array
    {
        $data = ['type' => (string) ($action['type'] ?? 'none')];
        $target = $action['target'] ?? null;
        if (is_int($target) || (is_string($target) && $target !== '')) {
            $data['target'] = (string) $target;
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $data
     * @return array{ok: bool, reason: string|null}
     */
    public function toRecipient(string $kind, int $recipientId, string $title, string $body, array $data): array
    {
        if (! $this->push->configured()) {
            return ['ok' => false, 'reason' => 'push_whatsapp_provider_not_configured'];
        }

        $tokens = PushToken::query()
            ->where('recipient_kind', $kind)
            ->where('recipient_id', $recipientId)
            ->get(['id', 'token']);

        if ($tokens->isEmpty()) {
            return ['ok' => false, 'reason' => 'no_push_token'];
        }

        $any = false;
        $reason = 'fcm_rejected';
        foreach ($tokens as $row) {
            $sent = $this->push->send((string) $row->token, $title, $body, $data);
            if ($sent['ok']) {
                $any = true;

                continue;
            }

            $reason = $sent['reason'] ?? 'fcm_rejected';
            if (in_array($reason, ['unregistered', 'not_found'], true)) {
                PushToken::query()->whereKey($row->id)->delete();
            }
        }

        if ($any) {
            return ['ok' => true, 'reason' => null];
        }

        Log::warning('device push failed', [
            'recipient_kind' => $kind,
            'recipient_id' => $recipientId,
            'reason' => $reason,
        ]);

        return ['ok' => false, 'reason' => $reason];
    }
}
