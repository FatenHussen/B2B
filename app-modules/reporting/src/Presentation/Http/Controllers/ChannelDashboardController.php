<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Http\ApiController;
use Modules\Core\Support\Tenant;
use Modules\Reporting\Domain\Models\DailySnapshot;
use Modules\Reporting\Domain\Models\ReportExport;
use Modules\Reporting\Presentation\Http\Requests\ExportReportRequest;

final class ChannelDashboardController extends ApiController
{
    /** @var list<string> */
    private const REPORT_TYPES = [
        'sales', 'products', 'retailers', 'reps', 'zones', 'inventory', 'finance', 'offers', 'operations',
    ];

    public function dashboard(): JsonResponse
    {
        $snapshot = DailySnapshot::query()->orderByDesc('snapshot_date')->orderByDesc('id')->first();
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];

        return $this->ok([
            'kpis' => $payload['kpis'] ?? [
                'sales' => 0,
                'orders_by_status' => [],
                'cash_collected' => 0,
                'receivables' => ['total' => 0, 'overdue' => 0],
                'retailers' => ['active' => 0, 'registered' => 0, 'new' => 0],
                'avg_order_value' => 0,
                'avg_confirm_time' => 0,
                'avg_delivery_time' => 0,
                'fill_rate' => 0,
            ],
            'alerts' => $payload['alerts'] ?? [],
            'charts' => $payload['charts'] ?? [
                'daily_sales' => [],
                'by_zone' => [],
                'top_products' => [],
                'top_retailers' => [],
                'rep_performance' => [],
                'heatmap' => [],
            ],
        ], [
            'snapshot_date' => $snapshot?->snapshot_date?->toDateString(),
        ]);
    }

    public function report(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, self::REPORT_TYPES, true)) {
            return $this->ok(['type' => $type, 'rows' => [], 'totals' => []]);
        }

        $snapshot = DailySnapshot::query()->orderByDesc('snapshot_date')->first();
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];
        $reports = is_array($payload['reports'] ?? null) ? $payload['reports'] : [];
        $block = is_array($reports[$type] ?? null) ? $reports[$type] : [];

        return $this->ok([
            'type' => $type,
            'rows' => $block['rows'] ?? [],
            'totals' => $block['totals'] ?? [],
        ]);
    }

    public function export(ExportReportRequest $request, string $type): JsonResponse
    {
        $data = $request->validated();
        $jobId = 'job_rep_exp_'.Str::uuid()->toString();
        ReportExport::query()->create([
            'supply_channel_id' => Tenant::currentId(),
            'job_id' => $jobId,
            'type' => $type,
            'format' => $data['format'],
            'filters' => $data['filters'] ?? [],
            'status' => 'queued',
        ]);

        return $this->ok(['job_id' => $jobId]);
    }

    public function margins(): JsonResponse
    {
        $snapshot = DailySnapshot::query()->orderByDesc('snapshot_date')->first();
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];
        $margins = is_array($payload['margins'] ?? null) ? $payload['margins'] : [];

        return $this->ok([
            'by_product' => $margins['by_product'] ?? [],
            'by_zone' => $margins['by_zone'] ?? [],
        ]);
    }
}
