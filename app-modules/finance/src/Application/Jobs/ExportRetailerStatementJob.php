<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Placeholder export job — EP-RT-053 returns job_id; download polling is a later ticket.
 */
final class ExportRetailerStatementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly int $retailerId,
        public readonly string $dateFrom,
        public readonly string $dateTo,
        public readonly ?int $channelId,
        public readonly string $format,
    ) {}

    public function handle(): void
    {
        // File generation lands with the shared exports poll surface.
    }
}
