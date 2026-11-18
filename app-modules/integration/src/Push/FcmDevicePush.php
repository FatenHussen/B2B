<?php

declare(strict_types=1);

namespace Modules\Integration\Push;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Modules\Core\Contracts\DevicePush;
use RuntimeException;
use Throwable;

/**
 * FCM HTTP v1. The `notification` block is what the OS draws on the lock
 * screen while the app is backgrounded or killed — the same shape as a
 * WhatsApp banner. `data` is only the tap target for the Flutter app.
 */
final class FcmDevicePush implements DevicePush
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function configured(): bool
    {
        return $this->credentials() !== null;
    }

    public function send(string $token, string $title, string $body, array $data): array
    {
        $creds = $this->credentials();
        if ($creds === null) {
            return ['ok' => false, 'reason' => 'push_whatsapp_provider_not_configured'];
        }

        try {
            $access = $this->accessToken($creds);
            $message = [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'sound' => 'default',
                        'notification_priority' => 'PRIORITY_HIGH',
                        'visibility' => 'PUBLIC',
                        'tag' => $this->tag($data),
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                        'apns-push-type' => 'alert',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ];
            if ($data !== []) {
                $message['data'] = $data;
            }

            $project = rawurlencode($creds['project_id']);
            $response = Http::timeout(10)
                ->withToken($access)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", [
                    'message' => $message,
                ]);
        } catch (Throwable $e) {
            Log::warning('fcm request failed', ['exception' => $e::class]);

            return ['ok' => false, 'reason' => 'fcm_unreachable'];
        }

        if ($response->successful()) {
            return ['ok' => true, 'reason' => null];
        }

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey($creds['client_email']));
        }

        return ['ok' => false, 'reason' => $this->reason($response)];
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    private function credentials(): ?array
    {
        $raw = config('firebase.credentials');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);
        if (str_starts_with($raw, '{')) {
            $json = $raw;
        } elseif (is_file($raw)) {
            $read = file_get_contents($raw);
            if ($read === false) {
                return null;
            }
            $json = $read;
        } else {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $project = $data['project_id'] ?? null;
        $email = $data['client_email'] ?? null;
        $key = $data['private_key'] ?? null;
        if (! is_string($project) || $project === ''
            || ! is_string($email) || $email === ''
            || ! is_string($key) || $key === '') {
            return null;
        }

        return [
            'project_id' => $project,
            'client_email' => $email,
            'private_key' => $key,
        ];
    }

    /**
     * @param  array{project_id: string, client_email: string, private_key: string}  $creds
     */
    private function accessToken(array $creds): string
    {
        $cacheKey = $this->cacheKey($creds['client_email']);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $unsigned = $this->b64(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$this->b64([
            'iss' => $creds['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        $private = openssl_pkey_get_private($creds['private_key']);
        if ($private === false) {
            throw new RuntimeException('Firebase private key is unreadable.');
        }

        $signature = '';
        if (openssl_sign($unsigned, $signature, $private, OPENSSL_ALGO_SHA256) !== true) {
            throw new RuntimeException('Firebase JWT could not be signed.');
        }

        $response = Http::timeout(10)->asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned.'.'.$this->b64raw($signature),
        ]);

        $access = $response->json('access_token');
        if (! $response->successful() || ! is_string($access) || $access === '') {
            throw new RuntimeException('Firebase access token was refused.');
        }

        Cache::put($cacheKey, $access, now()->addMinutes(50));

        return $access;
    }

    private function reason(Response $response): string
    {
        $code = null;
        $details = $response->json('error.details');
        if (is_array($details)) {
            foreach ($details as $detail) {
                if (is_array($detail) && is_string($detail['errorCode'] ?? null) && $detail['errorCode'] !== '') {
                    $code = $detail['errorCode'];
                    break;
                }
            }
        }

        if ($code === null) {
            $status = $response->json('error.status');
            $code = is_string($status) && $status !== '' ? $status : 'FCM_REJECTED';
        }

        return substr(strtolower($code), 0, 180);
    }

    /**
     * @param  array<string, int|string>  $data
     */
    private function b64(array $data): string
    {
        return $this->b64raw(json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function b64raw(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function cacheKey(string $email): string
    {
        return 'fcm.access.'.hash('sha256', $email);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function tag(array $data): string
    {
        $tag = $data['type'] ?? 'alert';
        if (($data['target'] ?? '') !== '') {
            $tag .= '-'.$data['target'];
        }

        return substr($tag, 0, 64);
    }
}
