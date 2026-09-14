<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * BE-T04 dispatches this empty. BE-T05 fills it.
 *
 * A false `running` state is rejected, and an orphan loop waiting for someone to finish
 * it is worse. The `provisioning → active` transition stays in BE-T05 where it belongs.
 *
 * `$publicId` is the `channel_provision_jobs.public_id` EP-AD-051 returns as
 * `provisioning_job_id` and EP-AD-053 retries.
 */
final class ProvisionChannel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $publicId,
    ) {}

    public function handle(): void
    {
        // Intentionally empty until BE-T05.
    }
}
