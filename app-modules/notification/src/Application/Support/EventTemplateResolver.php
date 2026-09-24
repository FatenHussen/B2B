<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Support;

use Modules\Core\Support\Tenant;
use Modules\Notification\Domain\Models\NotificationTemplate;

/**
 * Resolves channel notification templates (EP-SC-091) for system events.
 * Falls back to the caller defaults when the template is missing or disabled.
 */
final class EventTemplateResolver
{
    /**
     * @param  array<string, string>  $vars
     * @return array{title: string, body: string, channels: list<string>, enabled: bool}
     */
    public function resolve(int $channelId, string $eventKey, string $defaultTitle, string $defaultBody, array $vars = []): array
    {
        $row = $channelId > 0
            ? Tenant::as($channelId, fn () => NotificationTemplate::query()->where('event_key', $eventKey)->first())
            : null;

        if ($row === null) {
            return [
                'title' => $this->fill($defaultTitle, $vars),
                'body' => $this->fill($defaultBody, $vars),
                'channels' => ['in_app'],
                'enabled' => true,
            ];
        }

        if (! (bool) $row->enabled) {
            return [
                'title' => $this->fill($defaultTitle, $vars),
                'body' => $this->fill($defaultBody, $vars),
                'channels' => [],
                'enabled' => false,
            ];
        }

        $channels = is_array($row->channels) ? $row->channels : ['in_app'];

        return [
            'title' => $this->fill((string) ($row->title ?: $defaultTitle), $vars),
            'body' => $this->fill((string) ($row->body ?: $defaultBody), $vars),
            'channels' => array_values(array_map('strval', $channels)),
            'enabled' => true,
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function fill(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace(['{{'.$key.'}}', '{'.$key.'}'], $value, $text);
        }

        return $text;
    }
}
