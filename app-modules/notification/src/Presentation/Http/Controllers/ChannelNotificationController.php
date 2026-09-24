<?php

declare(strict_types=1);

namespace Modules\Notification\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Core\Support\Tenant;
use Modules\Notification\Application\Jobs\DeliverChannelNotificationJob;
use Modules\Notification\Domain\Models\ChannelNotification;
use Modules\Notification\Domain\Models\NotificationDeliveryLog;
use Modules\Notification\Domain\Models\NotificationTemplate;
use Modules\Notification\Presentation\Http\Requests\SendNotificationRequest;
use Modules\Notification\Presentation\Http\Requests\UpsertTemplateRequest;

final class ChannelNotificationController extends ApiController
{
    public function send(SendNotificationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $row = ChannelNotification::query()->create([
            'supply_channel_id' => Tenant::currentId(),
            'title' => $data['title'],
            'body' => $data['body'],
            'icon' => $data['icon'] ?? null,
            'image' => $data['image'] ?? null,
            'action' => $data['action'] ?? null,
            'targeting' => $data['targeting'],
            'channels' => $data['channels'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => 'queued',
        ]);

        NotificationDeliveryLog::query()->create([
            'supply_channel_id' => Tenant::currentId(),
            'notification_id' => $row->id,
            'recipient' => null,
            'template' => null,
            'status' => 'queued',
            'at' => now(),
        ]);

        $pending = DeliverChannelNotificationJob::dispatch((int) $row->id)->onQueue('notifications');
        if ($row->scheduled_at !== null && $row->scheduled_at->isFuture()) {
            $pending->delay($row->scheduled_at);
        }

        return $this->ok(['id' => (int) $row->id, 'status' => 'queued']);
    }

    public function templates(): JsonResponse
    {
        $rows = NotificationTemplate::query()->orderBy('event_key')->get()->map(fn (NotificationTemplate $row) => [
            'event_key' => $row->event_key,
            'title' => $row->title,
            'enabled' => (bool) $row->enabled,
            'channels' => $row->channels ?? [],
        ]);

        return $this->ok($rows->all());
    }

    public function upsertTemplate(UpsertTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        NotificationTemplate::query()->updateOrCreate(
            [
                'supply_channel_id' => Tenant::currentId(),
                'event_key' => $data['event_key'],
            ],
            [
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'enabled' => (bool) $data['enabled'],
                'channels' => $data['channels'],
            ],
        );

        return $this->ok(['event_key' => $data['event_key']]);
    }

    public function log(Request $request): JsonResponse
    {
        $page = NotificationDeliveryLog::query()
            ->orderByDesc('id')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return $this->paginated($page, fn (NotificationDeliveryLog $row) => [
            'at' => $row->at?->timezone('Asia/Damascus')->toIso8601String(),
            'recipient' => $row->recipient !== null ? (int) $row->recipient : null,
            'template' => $row->template,
            'status' => $row->status,
            'failure_reason' => $row->failure_reason,
        ]);
    }
}
