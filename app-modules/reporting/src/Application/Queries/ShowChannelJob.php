<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Queries;

use Modules\Reporting\Domain\Models\ReportExport;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowChannelJob
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $id): array
    {
        $export = ReportExport::query()->where('job_id', $id)->first();
        if ($export === null) {
            throw new NotFoundHttpException;
        }

        $status = $this->normalizeStatus((string) $export->status);
        $done = in_array($status, ['done', 'failed'], true);

        return [
            'id' => $export->job_id,
            'type' => $this->jobType((string) $export->type),
            'status' => $status,
            'progress' => match ($status) {
                'queued' => 0,
                'running' => 50,
                'done', 'failed' => 100,
                default => 0,
            },
            'result' => $status === 'done'
                ? ['download_url' => url('/storage/exports/'.$export->job_id.'.csv')]
                : null,
            'error' => $status === 'failed' ? 'export_failed' : null,
            'created_at' => $export->created_at?->timezone('Asia/Damascus')->toIso8601String(),
            'finished_at' => $done
                ? $export->updated_at?->timezone('Asia/Damascus')->toIso8601String()
                : null,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'ready' => 'done',
            'queued', 'running', 'done', 'failed' => $status,
            default => 'queued',
        };
    }

    private function jobType(string $stored): string
    {
        return match ($stored) {
            'catalog_export', 'catalog_import', 'report_export', 'price_list_schedule' => $stored,
            default => 'report_export',
        };
    }
}
