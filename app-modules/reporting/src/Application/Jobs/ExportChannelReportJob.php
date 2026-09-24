<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Support\Tenant;
use Modules\Reporting\Domain\Models\DailySnapshot;
use Modules\Reporting\Domain\Models\ReportExport;

final class ExportChannelReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $jobId) {}

    public function handle(): void
    {
        $export = ReportExport::query()->where('job_id', $this->jobId)->first();
        if ($export === null) {
            return;
        }

        $export->forceFill(['status' => 'running'])->save();

        try {
            Tenant::as((int) $export->supply_channel_id, function () use ($export): void {
                $filters = is_array($export->filters) ? $export->filters : [];
                $snapshotQuery = DailySnapshot::query()->orderByDesc('snapshot_date')->orderByDesc('id');
                $from = $filters['date_from'] ?? null;
                $to = $filters['date_to'] ?? null;
                if (is_string($from) && $from !== '') {
                    $snapshotQuery->whereDate('snapshot_date', '>=', $from);
                }
                if (is_string($to) && $to !== '') {
                    $snapshotQuery->whereDate('snapshot_date', '<=', $to);
                }
                $snapshot = $snapshotQuery->first()
                    ?? DailySnapshot::query()->orderByDesc('snapshot_date')->orderByDesc('id')->first();

                $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];
                $reports = is_array($payload['reports'] ?? null) ? $payload['reports'] : [];
                $block = is_array($reports[$export->type] ?? null) ? $reports[$export->type] : [];
                $rows = is_array($block['rows'] ?? null) ? $block['rows'] : [];

                $handle = fopen('php://temp', 'r+');
                $headers = $rows === [] ? ['empty'] : array_keys(is_array($rows[0] ?? null) ? $rows[0] : ['value' => null]);
                fputcsv($handle, $headers);
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    fputcsv($handle, array_map(
                        static fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v),
                        array_values($row),
                    ));
                }
                rewind($handle);
                Storage::disk('local')->put('exports/'.$export->job_id.'.csv', stream_get_contents($handle) ?: '');
                fclose($handle);
            });

            $export->forceFill(['status' => 'done'])->save();
        } catch (\Throwable $e) {
            $export->forceFill(['status' => 'failed'])->save();
            throw $e;
        }
    }
}
