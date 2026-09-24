<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Support\Str;
use Modules\Catalog\Application\Jobs\ExportCatalogJob;
use Modules\Core\Contracts\ChannelJobRegistry;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\Tenant;

final class ExportCatalog
{
    public function __construct(
        private readonly RecordsAudit $audit,
        private readonly ChannelJobRegistry $jobs,
    ) {}

    /**
     * @return array{job_id: string}
     */
    public function __invoke(object $actor, ?string $status = null): array
    {
        $jobId = 'job_catalog_'.Str::lower((string) Str::ulid());
        $channelId = (int) Tenant::currentId();

        $this->jobs->enqueue(
            $channelId,
            $jobId,
            'catalog_export',
            'csv',
            $status !== null ? ['status' => $status] : [],
        );

        ExportCatalogJob::dispatch($jobId, $channelId, $status)
            ->onQueue('exports');

        $this->audit->record('catalog.export', $actor, 'catalog_export', null, [
            'after' => ['job_id' => $jobId],
        ], $channelId);

        return ['job_id' => $jobId];
    }
}
