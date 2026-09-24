<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Channel-scoped async export rows polled by EP-SC-082 GET /channel/jobs/{id}.
 *
 * Reporting owns `report_exports`. Catalog (and others) enqueue and flip status
 * through this contract so no Domain module imports a Coordination model.
 */
interface ChannelJobRegistry
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function enqueue(int $channelId, string $jobId, string $type, string $format, array $filters = []): void;

    public function mark(string $jobId, string $status): void;
}
