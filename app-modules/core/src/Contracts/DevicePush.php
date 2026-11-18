<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A banner on the phone (FCM). The client apiKey cannot send; the adapter
 * holds the Firebase service account. `configured()` is false until that
 * file is present, and callers keep the honest "not configured" log.
 */
interface DevicePush
{
    public function configured(): bool;

    /**
     * One device token. `data` values are strings — FCM rejects other types.
     *
     * @param  array<string, string>  $data
     * @return array{ok: bool, reason: string|null}
     */
    public function send(string $token, string $title, string $body, array $data): array;
}
