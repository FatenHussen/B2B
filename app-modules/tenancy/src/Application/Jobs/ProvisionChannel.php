<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tenancy\Application\Actions\RunProvisioning;

/**
 * BE-T05. Dispatched empty by BE-T04; this ticket fills the body.
 *
 * `$tries = 1`: a failure is recorded on the row and retried through EP-AD-053,
 * which copies completed_steps, rather than by Horizon duplicating a step.
 */
final class ProvisionChannel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $publicId,
    ) {}

    public function handle(RunProvisioning $run): void
    {
        $run($this->publicId);
    }
}
