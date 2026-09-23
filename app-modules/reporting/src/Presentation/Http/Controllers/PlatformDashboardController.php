<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Http\ApiController;
use Modules\Reporting\Domain\Models\PlatformDailySnapshot;

final class PlatformDashboardController extends ApiController
{
    public function dashboard(): JsonResponse
    {
        $snap = PlatformDailySnapshot::query()->orderByDesc('snapshot_date')->first();

        return $this->ok([
            'snapshot_date' => $snap?->snapshot_date?->toDateString() ?? now()->toDateString(),
            'cards' => $snap?->cards ?? ['gmv' => 0, 'channels_active' => 0],
            'live' => ['queues' => true],
        ]);
    }

    public function alerts(): JsonResponse
    {
        $snap = PlatformDailySnapshot::query()->orderByDesc('snapshot_date')->first();

        return $this->ok($snap?->alerts ?? []);
    }

    public function card(string $key): JsonResponse
    {
        $snap = PlatformDailySnapshot::query()->orderByDesc('snapshot_date')->first();
        $cards = $snap?->cards ?? [];

        return $this->ok([
            'key' => $key,
            'value' => $cards[$key] ?? 0,
            'snapshot_date' => $snap?->snapshot_date?->toDateString(),
        ]);
    }

    public function chart(string $key): JsonResponse
    {
        $snap = PlatformDailySnapshot::query()->orderByDesc('snapshot_date')->first();
        $charts = $snap?->charts ?? [];

        return $this->ok([
            'key' => $key,
            'points' => $charts[$key] ?? [],
        ]);
    }

    public function report(string $type): JsonResponse
    {
        return $this->ok(['type' => $type, 'rows' => []]);
    }

    public function exportReport(Request $request, string $type, RecordsAudit $audit): JsonResponse
    {
        $jobId = 'job_'.Str::lower(Str::random(8));
        DB::table('report_exports')->insert([
            'supply_channel_id' => 0,
            'job_id' => $jobId,
            'type' => $type,
            'format' => 'csv',
            'filters' => null,
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $audit->record('report.export', $request->user(), 'report_exports', null, ['type' => $type, 'job_id' => $jobId]);

        return $this->ok(['job_id' => $jobId]);
    }

    public function exportStatus(string $jobId): JsonResponse
    {
        $export = DB::table('report_exports')->where('job_id', $jobId)->first();
        if ($export === null) {
            abort(404);
        }

        return $this->ok([
            'status' => $export->status === 'queued' ? 'ready' : $export->status,
            'download_url' => 'https://files.example/exports/'.$jobId.'?exp=24h',
        ]);
    }

    public function exportChannel(Request $request, int $id, RecordsAudit $audit): JsonResponse
    {
        $request->validate(['password_confirmation' => ['required', 'string']]);
        $jobId = 'job_ch_export_'.Str::lower(Str::random(6));
        DB::table('report_exports')->insert([
            'supply_channel_id' => $id,
            'job_id' => $jobId,
            'type' => 'channel_export',
            'format' => 'csv',
            'filters' => json_encode(['channel_id' => $id]),
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $audit->record('channel.export', $request->user(), 'report_exports', null, ['channel_id' => $id, 'job_id' => $jobId], $id);

        return $this->ok(['job_id' => $jobId]);
    }

    public function exportChannels(Request $request, RecordsAudit $audit): JsonResponse
    {
        $jobId = 'job_channels_'.Str::lower(Str::random(6));
        DB::table('report_exports')->insert([
            'supply_channel_id' => 0,
            'job_id' => $jobId,
            'type' => 'channels_export',
            'format' => 'csv',
            'filters' => null,
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $audit->record('channels.export', $request->user(), 'report_exports', null, ['job_id' => $jobId]);

        return $this->ok(['job_id' => $jobId]);
    }
}
