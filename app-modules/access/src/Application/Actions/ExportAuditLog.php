<?php

declare(strict_types=1);

namespace Modules\Access\Application\Actions;

use Illuminate\Support\Str;
use Modules\Access\Application\Jobs\ExportAuditLogJob;
use Modules\Core\Contracts\RecordsAudit;

final class ExportAuditLog
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array{filters?: array<string, mixed>, format?: string}  $data
     * @return array{job_id: string}
     */
    public function __invoke(object $actor, array $data): array
    {
        $jobId = 'job_audit_'.Str::lower((string) Str::ulid());

        ExportAuditLogJob::dispatch(
            $jobId,
            $data['filters'] ?? [],
            (int) $actor->getAuthIdentifier(),
            $data['format'] ?? 'xlsx',
        )->onQueue('exports');

        $this->audit->record(
            'audit.export',
            $actor,
            'audit_export',
            null,
            ['after' => ['job_id' => $jobId, 'filters' => $data['filters'] ?? []]],
        );

        return ['job_id' => $jobId];
    }
}
