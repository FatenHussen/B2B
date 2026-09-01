<?php

declare(strict_types=1);

namespace Modules\Access\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Models\AuditLog;

final class ExportAuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly string $jobId,
        public readonly array $filters,
        public readonly int $actorId,
        public readonly string $format = 'xlsx',
    ) {}

    public function handle(): void
    {
        $query = AuditLog::query()->orderBy('created_at');

        if (isset($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (isset($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }
        if (isset($this->filters['actor'])) {
            $query->where('actor_id', $this->filters['actor']);
        }
        if (isset($this->filters['action'])) {
            $query->where('action', $this->filters['action']);
        }
        if (isset($this->filters['channel_id'])) {
            $query->where('channel_id', $this->filters['channel_id']);
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['at', 'actor', 'action', 'entity_type', 'entity_id', 'ip']);
        foreach ($query->cursor() as $log) {
            fputcsv($handle, [
                $log->created_at?->toIso8601String(),
                $log->actor_id,
                $log->action,
                $log->subject_type,
                $log->subject_id,
                $log->ip,
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        Storage::disk('local')->put('exports/'.$this->jobId.'.csv', $csv);
    }
}
