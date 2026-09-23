<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Content\Domain\Models\AppVersion;
use Modules\Core\Contracts\RequestsDualApproval;
use Modules\Core\Http\ApiController;

final class PlatformAppVersionController extends ApiController
{
    public function index(): JsonResponse
    {
        $rows = AppVersion::query()->orderByDesc('id')->limit(100)->get()->map(fn (AppVersion $v) => [
            'id' => $v->id,
            'app' => $v->app,
            'platform' => $v->platform,
            'version' => $v->version,
            'force_update' => $v->force_update,
        ]);

        return $this->ok($rows->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app' => ['required', 'string'],
            'platform' => ['required', 'string'],
            'version' => ['required', 'string'],
            'build' => ['sometimes', 'string'],
            'min_supported' => ['sometimes', 'string'],
            'release_notes' => ['sometimes', 'string'],
            'rollout' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'store_url' => ['sometimes', 'string'],
        ]);

        $row = AppVersion::query()->create($data + ['force_update' => false]);

        return $this->created(['id' => $row->id]);
    }

    public function forceUpdate(Request $request, int $id, RequestsDualApproval $dual): JsonResponse
    {
        $row = AppVersion::query()->findOrFail($id);
        $data = $request->validate([
            'approval_request_id' => ['sometimes', 'integer'],
            'approval_reason' => ['required_with:approval_request_id', 'string'],
        ]);

        $decision = $dual->gate(
            $request->user(),
            'ad.content.force_update',
            'app_version.force_update',
            ['version_id' => $id],
            isset($data['approval_request_id']) ? (int) $data['approval_request_id'] : null,
            $data['approval_reason'] ?? null,
        );

        if (! $decision->execute) {
            return $this->ok(['approval_request_id' => $decision->approvalRequestId]);
        }

        $row->force_update = true;
        $row->save();

        return $this->ok(['id' => $row->id, 'force_update' => true]);
    }
}
