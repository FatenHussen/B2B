<?php

declare(strict_types=1);

namespace Modules\Access\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\AuditTrail;

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

    public function handle(AuditTrail $audit): void
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['at', 'actor', 'action', 'entity_type', 'entity_id', 'ip']);

        foreach ($audit->stream([
            'actor' => $this->filters['actor'] ?? null,
            'action' => $this->filters['action'] ?? null,
            'channel_id' => $this->filters['channel_id'] ?? null,
            'date_from' => $this->filters['date_from'] ?? null,
            'date_to' => $this->filters['date_to'] ?? null,
        ]) as $entry) {
            fputcsv($handle, [
                $entry['at'],
                $entry['actor'],
                $entry['action'],
                $entry['entity_type'],
                $entry['entity_id'],
                $entry['ip'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        Storage::disk('local')->put('exports/'.$this->jobId.'.csv', $csv);
    }
}
