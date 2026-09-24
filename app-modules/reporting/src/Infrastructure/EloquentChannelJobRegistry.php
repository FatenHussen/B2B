<?php

declare(strict_types=1);

namespace Modules\Reporting\Infrastructure;

use Modules\Core\Contracts\ChannelJobRegistry;
use Modules\Core\Support\Tenant;
use Modules\Reporting\Domain\Models\ReportExport;

final class EloquentChannelJobRegistry implements ChannelJobRegistry
{
    public function enqueue(int $channelId, string $jobId, string $type, string $format, array $filters = []): void
    {
        Tenant::as($channelId, function () use ($channelId, $jobId, $type, $format, $filters): void {
            ReportExport::query()->create([
                'supply_channel_id' => $channelId,
                'job_id' => $jobId,
                'type' => $type,
                'format' => $format,
                'filters' => $filters,
                'status' => 'queued',
            ]);
        });
    }

    public function mark(string $jobId, string $status): void
    {
        $export = ReportExport::query()->where('job_id', $jobId)->first();
        $export?->forceFill(['status' => $status])->save();
    }
}
