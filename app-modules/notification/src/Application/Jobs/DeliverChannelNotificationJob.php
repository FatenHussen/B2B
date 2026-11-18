<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\RetailerGroupDirectory;
use Modules\Core\Support\Tenant;
use Modules\Notification\Application\Support\InboxWriter;
use Modules\Notification\Application\Support\PushDispatcher;
use Modules\Notification\Domain\Models\ChannelNotification;
use Modules\Notification\Domain\Models\NotificationDeliveryLog;

final class DeliverChannelNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $notificationId) {}

    public function handle(
        RetailerDirectory $retailers,
        RetailerGroupDirectory $groups,
        InboxWriter $inbox,
        ChannelDirectory $channels,
        PushDispatcher $push,
    ): void {
        $row = ChannelNotification::query()->find($this->notificationId);
        if ($row === null) {
            return;
        }

        if ($row->scheduled_at !== null && $row->scheduled_at->isFuture()) {
            return;
        }

        Tenant::as((int) $row->supply_channel_id, function () use ($row, $retailers, $groups, $inbox, $channels, $push): void {
            if ($this->inQuietHours($channels->settings((int) $row->supply_channel_id))) {
                $row->forceFill(['status' => 'queued'])->save();
                self::dispatch($this->notificationId)
                    ->onQueue('notifications')
                    ->delay(now('Asia/Damascus')->addHour());

                return;
            }

            $recipients = $this->resolveRecipients($row->targeting ?? [], $retailers, $groups);
            $via = is_array($row->channels) ? $row->channels : [];
            $action = is_array($row->action) ? $row->action : ['type' => 'none', 'target' => null];
            $icon = is_string($row->icon) && $row->icon !== '' ? $row->icon : 'bell';
            $wantsInApp = in_array('in_app', $via, true);
            $wantsPush = in_array('push', $via, true);
            $wantsWhatsapp = in_array('whatsapp', $via, true);
            $target = $action['target'] ?? null;
            $pushData = PushDispatcher::dataFromAction([
                'type' => (string) ($action['type'] ?? 'none'),
                'target' => is_int($target) || is_string($target) ? $target : null,
            ]);

            NotificationDeliveryLog::query()
                ->where('notification_id', $row->id)
                ->whereNull('recipient')
                ->delete();

            if ($recipients === []) {
                NotificationDeliveryLog::query()->create([
                    'supply_channel_id' => $row->supply_channel_id,
                    'notification_id' => $row->id,
                    'recipient' => null,
                    'template' => null,
                    'status' => 'sent',
                    'at' => now(),
                ]);
                $row->forceFill(['status' => 'sent'])->save();

                return;
            }

            $statuses = [];
            foreach ($recipients as $userId) {
                if ($wantsInApp) {
                    $inbox->write(
                        'retailer',
                        $userId,
                        (int) $row->supply_channel_id,
                        $icon,
                        (string) $row->title,
                        (string) $row->body,
                        [
                            'type' => (string) ($action['type'] ?? 'none'),
                            'target' => $action['target'] ?? null,
                        ],
                    );
                }

                $reasons = [];
                $okCount = $wantsInApp ? 1 : 0;
                $askCount = ($wantsInApp ? 1 : 0) + ($wantsPush ? 1 : 0) + ($wantsWhatsapp ? 1 : 0);

                if ($wantsPush) {
                    $sent = $push->toRecipient('retailer', $userId, (string) $row->title, (string) $row->body, $pushData);
                    if ($sent['ok']) {
                        $okCount++;
                    } elseif ($sent['reason'] !== null) {
                        $reasons[] = $sent['reason'];
                    }
                } elseif ($wantsInApp) {
                    // Inbox is already delivered. A phone banner is best-effort once
                    // Firebase is configured, and a missing token must not turn `sent` into `partial`.
                    $push->toRecipient('retailer', $userId, (string) $row->title, (string) $row->body, $pushData);
                }
                if ($wantsWhatsapp) {
                    $reasons[] = 'push_whatsapp_provider_not_configured';
                }
                if ($askCount === 0) {
                    $reasons[] = 'no_delivery_channel';
                }

                $status = match (true) {
                    $askCount === 0 || $okCount === 0 => 'failed',
                    $okCount < $askCount => 'partial',
                    default => 'sent',
                };

                NotificationDeliveryLog::query()->create([
                    'supply_channel_id' => $row->supply_channel_id,
                    'notification_id' => $row->id,
                    'recipient' => $userId,
                    'template' => null,
                    'status' => $status,
                    'failure_reason' => $reasons[0] ?? null,
                    'at' => now(),
                ]);
                $statuses[] = $status;
            }

            $unique = array_values(array_unique($statuses));
            $campaignStatus = match (true) {
                $unique === [] || $unique === ['sent'] => 'sent',
                $unique === ['failed'] => 'failed',
                default => 'partial',
            };
            $row->forceFill(['status' => $campaignStatus])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function inQuietHours(array $settings): bool
    {
        $quiet = $settings['quiet_hours'] ?? null;
        if (! is_array($quiet)) {
            return false;
        }
        $from = is_string($quiet['from'] ?? null) ? $quiet['from'] : (is_string($quiet['start'] ?? null) ? $quiet['start'] : null);
        $to = is_string($quiet['to'] ?? null) ? $quiet['to'] : (is_string($quiet['end'] ?? null) ? $quiet['end'] : null);
        if ($from === null || $to === null) {
            return false;
        }

        $now = Carbon::now('Asia/Damascus');
        $fromAt = Carbon::parse($now->toDateString().' '.$from, 'Asia/Damascus');
        $toAt = Carbon::parse($now->toDateString().' '.$to, 'Asia/Damascus');
        if ($toAt->lte($fromAt)) {
            return $now->gte($fromAt) || $now->lt($toAt);
        }

        return $now->gte($fromAt) && $now->lt($toAt);
    }

    /**
     * @param  array<string, mixed>  $targeting
     * @return list<int>
     */
    private function resolveRecipients(
        array $targeting,
        RetailerDirectory $retailers,
        RetailerGroupDirectory $groups,
    ): array {
        $type = (string) ($targeting['type'] ?? 'all');
        $ids = array_values(array_map('intval', is_array($targeting['ids'] ?? null) ? $targeting['ids'] : []));

        return match ($type) {
            'zone' => $retailers->appUserIdsInZones($ids),
            'activity', 'activity_type' => $retailers->appUserIdsByActivityTypes($ids),
            'group' => $groups->appUserIdsInGroups($ids),
            'retailer' => $retailers->appUserIdsForProfiles($ids),
            'user' => $ids,
            default => $retailers->allAppUserIds(),
        };
    }
}
