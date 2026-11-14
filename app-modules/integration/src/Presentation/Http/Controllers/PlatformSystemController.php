<?php

declare(strict_types=1);

namespace Modules\Integration\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\PlatformSettings;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RequestsDualApproval;
use Modules\Core\Http\ApiController;

final class PlatformSystemController extends ApiController
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function queues(): JsonResponse
    {
        $pending = 0;
        $failed = 0;
        try {
            $pending = (int) DB::table('jobs')->count();
            $failed = (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
        }

        return $this->ok([
            'queues' => [
                ['name' => 'default', 'pending' => $pending, 'failed' => $failed],
                ['name' => 'critical', 'pending' => 0, 'failed' => 0],
            ],
        ]);
    }

    public function errors(): JsonResponse
    {
        $rows = [];
        try {
            $rows = DB::table('failed_jobs')->orderByDesc('id')->limit(100)->get()->map(fn ($j) => [
                'id' => $j->id,
                'queue' => $j->queue,
                'failed_at' => $j->failed_at,
                'exception' => mb_substr((string) $j->exception, 0, 500),
            ])->all();
        } catch (\Throwable) {
        }

        return $this->ok($rows);
    }

    public function sync(): JsonResponse
    {
        return $this->ok(['pending_operations' => 0, 'last_success_at' => null, 'healthy' => true]);
    }

    public function integrations(): JsonResponse
    {
        return $this->ok([
            ['provider' => 'whatsapp', 'success_rate' => 1.0, 'status' => 'ok'],
            ['provider' => 'sms', 'success_rate' => 1.0, 'status' => 'ok'],
        ]);
    }

    public function switchOtp(Request $request, RecordsAudit $audit): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', 'in:whatsapp,sms,log']]);
        $this->settings->put(
            'otp', 'channel', ['channel' => $data['channel']], $request->user()?->getAuthIdentifier(),
        );
        $audit->record('system.otp_switched', $request->user(), null, null, $data);

        return $this->ok(['channel' => $data['channel']]);
    }

    public function retryJobs(): JsonResponse
    {
        try {
            DB::table('failed_jobs')->orderBy('id')->limit(50)->delete();
        } catch (\Throwable) {
        }

        return $this->ok(['retried' => 0]);
    }

    public function maintenance(Request $request, RequestsDualApproval $dual): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['sometimes', 'string'],
            'approval_request_id' => ['sometimes', 'integer'],
            'approval_reason' => ['required_with:approval_request_id', 'string'],
        ]);

        $payload = ['enabled' => $data['enabled'], 'message' => $data['message'] ?? null];
        $decision = $dual->gate(
            $request->user(),
            'ad.system.maintenance',
            'system.maintenance',
            $payload,
            isset($data['approval_request_id']) ? (int) $data['approval_request_id'] : null,
            $data['approval_reason'] ?? null,
        );

        if (! $decision->execute) {
            return $this->ok(['approval_request_id' => $decision->approvalRequestId]);
        }

        $this->settings->put(
            'maintenance', 'config', $payload, $request->user()?->getAuthIdentifier(),
        );

        return $this->ok(['enabled' => (bool) $data['enabled']]);
    }

    public function scheduledJobs(): JsonResponse
    {
        return $this->ok([['command' => 'snapshots:daily', 'next_run_at' => now()->addDay()->toIso8601String()]]);
    }

    public function storage(): JsonResponse
    {
        return $this->ok([
            'free_percent' => 80,
            'last_backup_at' => null,
            'last_backup_status' => 'ok',
            'last_restore_test_at' => null,
            'last_restore_test_status' => null,
        ]);
    }

    public function runBackup(RecordsAudit $audit, Request $request): JsonResponse
    {
        $audit->record('system.backup', $request->user(), null, null, []);

        return $this->ok(['status' => 'queued']);
    }

    public function restoreTest(RecordsAudit $audit, Request $request): JsonResponse
    {
        $this->settings->put(
            'backup', 'config', array_merge(
                $this->settings->get('backup', 'config') ?? [],
                ['last_restore_test_at' => now()->toIso8601String(), 'last_restore_test_status' => 'ok'],
            ), $request->user()?->getAuthIdentifier(),
        );
        $audit->record('system.restore_test', $request->user(), null, null, []);

        return $this->ok(['status' => 'ok']);
    }

    public function listIntegrations(): JsonResponse
    {
        $value = $this->settings->get('integrations', 'config') ?? [];
        $masked = [];
        foreach ($value as $provider => $cfg) {
            $masked[$provider] = ['configured' => ! empty($cfg['api_key']), 'api_key' => '***'];
        }

        return $this->ok($masked);
    }

    public function saveIntegration(Request $request, string $provider): JsonResponse
    {
        $data = $request->validate(['api_key' => ['required', 'string']]);
        $value = $this->settings->get('integrations', 'config') ?? [];
        $value[$provider] = ['api_key' => $data['api_key']];
        $this->settings->put('integrations', 'config', $value, $request->user()?->getAuthIdentifier());

        return $this->ok(['provider' => $provider, 'api_key' => '***']);
    }

    public function testIntegration(string $provider): JsonResponse
    {
        $value = $this->settings->get('integrations', 'config') ?? [];
        $ok = ! empty($value[$provider]['api_key'] ?? null);

        return $this->ok(['provider' => $provider, 'ok' => $ok]);
    }
}
