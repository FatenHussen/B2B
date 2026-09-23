<?php

declare(strict_types=1);

namespace Modules\Notification\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\RequestsDualApproval;
use Modules\Core\Http\ApiController;
use Modules\Notification\Domain\Models\PlatformCampaign;
use Modules\Notification\Domain\Models\PlatformNotificationTemplate;

final class PlatformNotificationController extends ApiController
{
    public function broadcast(Request $request, RequestsDualApproval $dual): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
            'targeting' => ['sometimes', 'array'],
            'approval_request_id' => ['sometimes', 'integer'],
            'approval_reason' => ['required_with:approval_request_id', 'string'],
        ]);

        $payload = ['title' => $data['title'], 'body' => $data['body']];
        $decision = $dual->gate(
            $request->user(),
            'ad.notify.broadcast',
            'platform.notify.broadcast',
            $payload,
            isset($data['approval_request_id']) ? (int) $data['approval_request_id'] : null,
            $data['approval_reason'] ?? null,
        );

        if (! $decision->execute) {
            $campaign = new PlatformCampaign;
            $campaign->fill([
                'title' => $data['title'],
                'body' => $data['body'],
                'targeting' => $data['targeting'] ?? [],
            ]);
            $campaign->status = 'pending_approval';
            $campaign->save();

            return $this->ok(['approval_request_id' => $decision->approvalRequestId, 'campaign_id' => $campaign->id]);
        }

        return $this->ok(['status' => 'queued']);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
            'targeting' => ['sometimes', 'array'],
            'channels' => ['sometimes', 'array'],
            'scheduled_at' => ['sometimes', 'date'],
        ]);

        $campaign = new PlatformCampaign;
        $campaign->fill($data);
        $campaign->status = 'draft';
        $campaign->save();

        return $this->created(['id' => $campaign->id]);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate(['targeting' => ['sometimes', 'array']]);

        return $this->ok(['estimated_recipients' => 0]);
    }

    public function templates(): JsonResponse
    {
        $rows = PlatformNotificationTemplate::query()->orderBy('key')->get()->map(fn ($t) => [
            'key' => $t->key,
            'title' => $t->title,
            'body' => $t->body,
        ]);

        return $this->ok($rows->all());
    }

    public function updateTemplates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*.key' => ['required', 'string'],
            'templates.*.title' => ['required', 'string'],
            'templates.*.body' => ['required', 'string'],
        ]);

        foreach ($data['templates'] as $tpl) {
            PlatformNotificationTemplate::query()->updateOrCreate(
                ['key' => $tpl['key']],
                ['title' => $tpl['title'], 'body' => $tpl['body']],
            );
        }

        return $this->ok(['updated' => count($data['templates'])]);
    }

    public function log(): JsonResponse
    {
        return $this->ok([]);
    }

    public function campaigns(): JsonResponse
    {
        $rows = PlatformCampaign::query()->orderByDesc('id')->limit(100)->get()->map(fn (PlatformCampaign $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'status' => $c->status,
            'scheduled_at' => $c->scheduled_at?->toIso8601String(),
            'estimated_recipients' => 0,
        ]);

        return $this->ok($rows->all());
    }

    public function showCampaign(int $id): JsonResponse
    {
        $c = PlatformCampaign::query()->findOrFail($id);

        return $this->ok([
            'id' => $c->id,
            'title' => $c->title,
            'status' => $c->status,
            'stats' => $c->stats ?? ['sent' => 0, 'delivered' => 0, 'read' => 0, 'failed' => 0],
        ]);
    }

    public function notifyManagers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_ids' => ['required', 'array', 'max:50'],
            'channel_ids.*' => ['integer'],
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
        ]);

        return $this->ok(['notified' => count($data['channel_ids'])]);
    }
}
