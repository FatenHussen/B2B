<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Illuminate\Support\Str;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Tenancy\Application\Jobs\ProvisionChannel;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelProvisionJob;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * EP-AD-053 — re-dispatch provisioning. Safe to call repeatedly: a live run is
 * 409, a finished channel returns the job it already has, a queued job is
 * re-dispatched as itself, a failed run copies completed_steps onto a new job.
 */
final class RetryProvisioning
{
    /**
     * @return array{job_id: string}
     */
    public function __invoke(SupplyChannel $channel): array
    {
        return Tenant::as((int) $channel->id, function () use ($channel): array {
            $latest = ChannelProvisionJob::query()->latest('id')->first();

            if ($latest !== null && $latest->status === 'running') {
                throw DomainException::of(
                    ErrorCode::OperationInProgress,
                    __('tenancy.provisioning_in_progress'),
                );
            }

            if ($channel->status === ChannelStatus::Active && $latest !== null && $latest->status === 'complete') {
                return ['job_id' => $latest->public_id];
            }

            if ($latest !== null && $latest->status === 'queued') {
                ProvisionChannel::dispatch($latest->public_id)->onQueue('provisioning');

                return ['job_id' => $latest->public_id];
            }

            $payload = is_array($latest?->payload) ? $latest->payload : [];
            $completed = is_array($latest?->completed_steps) ? $latest->completed_steps : [];
            $publicId = 'job_prov_'.Str::lower((string) Str::ulid());

            ChannelProvisionJob::query()->create([
                'public_id' => $publicId,
                'channel_id' => $channel->id,
                'status' => 'queued',
                'payload' => $payload,
                'completed_steps' => $completed,
            ]);

            ProvisionChannel::dispatch($publicId)->onQueue('provisioning');

            return ['job_id' => $publicId];
        });
    }
}
