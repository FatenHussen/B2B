<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Modules\Core\Domain\Events\ChannelManagerInvited;
use Modules\Core\Support\Tenant;
use Modules\Tenancy\Application\ProvisioningActor;
use Modules\Tenancy\Application\Services\ChannelLifecycle;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelLimit;
use Modules\Tenancy\Domain\Models\ChannelProvisionJob;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;

/**
 * BE-T05 — materialise a channel that CreateChannel left in `provisioning`.
 *
 * Each step is recorded on the job before the next one starts, so a retry after a
 * partial failure continues from what is left. The channel stays `provisioning`
 * until every step has landed — it cannot accept orders in between (isActive is
 * false, and there is no default warehouse until that step commits).
 *
 * What this module owns: limits (already inserted at create; re-applied if missing),
 * one default warehouse, the manager invite event, and the move to `active`.
 *
 * What it does not own: `channel_zone` (Reference — BE-T08), channel_users / the
 * WhatsApp send (Identity listens to ChannelManagerInvited), per-channel role rows
 * (Access seeds `channel_manager` globally), feature flags (BE-T10).
 */
final class RunProvisioning
{
    public const STEP_LIMITS = 'limits';

    public const STEP_WAREHOUSE = 'warehouse';

    public const STEP_INVITE = 'invite';

    public const STEP_ACTIVATE = 'activate';

    public function __construct(private readonly ChannelLifecycle $lifecycle) {}

    public function __invoke(string $publicId): void
    {
        $job = Tenant::withoutScope(
            fn (): ?ChannelProvisionJob => ChannelProvisionJob::query()->where('public_id', $publicId)->first(),
        );

        if ($job === null) {
            return;
        }

        Tenant::as((int) $job->channel_id, function () use ($job): void {
            $this->run($job->fresh() ?? $job);
        });
    }

    private function run(ChannelProvisionJob $job): void
    {
        $channel = SupplyChannel::query()->find($job->channel_id);
        if ($channel === null) {
            $this->fail($job, 'channel missing');

            return;
        }

        $job->update([
            'status' => 'running',
            'started_at' => $job->started_at ?? now(),
            'attempts' => (int) $job->attempts + 1,
            'error' => null,
        ]);

        try {
            $this->ensureLimits($job, $channel);
            $this->ensureWarehouse($job, $channel);
            $this->inviteManager($job, $channel);
            $this->activate($job, $channel);

            $job->update([
                'status' => 'complete',
                'finished_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $this->fail($job, $e->getMessage());
        }
    }

    private function ensureLimits(ChannelProvisionJob $job, SupplyChannel $channel): void
    {
        if ($this->done($job, self::STEP_LIMITS)) {
            return;
        }

        $limits = $job->payload['limits'] ?? null;
        if (! is_array($limits)) {
            throw new \RuntimeException('provisioning payload has no limits');
        }

        ChannelLimit::query()->updateOrCreate(
            ['channel_id' => $channel->id],
            [
                'users' => (int) $limits['users'],
                'warehouses' => (int) $limits['warehouses'],
                'reps' => (int) $limits['reps'],
                'skus' => (int) $limits['skus'],
                'storage_mb' => (int) $limits['storage_mb'],
            ],
        );

        $this->complete($job, self::STEP_LIMITS);
    }

    private function ensureWarehouse(ChannelProvisionJob $job, SupplyChannel $channel): void
    {
        if ($this->done($job, self::STEP_WAREHOUSE)) {
            return;
        }

        if (! Warehouse::query()->where('channel_id', $channel->id)->exists()) {
            Warehouse::query()->create([
                'channel_id' => $channel->id,
                'name' => $channel->name,
            ]);
        }

        $this->complete($job, self::STEP_WAREHOUSE);
    }

    private function inviteManager(ChannelProvisionJob $job, SupplyChannel $channel): void
    {
        if ($this->done($job, self::STEP_INVITE)) {
            return;
        }

        $manager = $job->payload['manager'] ?? null;
        if (! is_array($manager) || ! is_string($manager['phone'] ?? null) || $manager['phone'] === '') {
            throw new \RuntimeException('provisioning payload has no manager');
        }

        event(new ChannelManagerInvited(
            channelId: (int) $channel->id,
            name: (string) ($manager['name'] ?? ''),
            phone: (string) $manager['phone'],
            email: isset($manager['email']) && is_string($manager['email']) ? $manager['email'] : null,
            inviteVia: (string) ($manager['invite_via'] ?? 'whatsapp'),
        ));

        $this->complete($job, self::STEP_INVITE);
    }

    private function activate(ChannelProvisionJob $job, SupplyChannel $channel): void
    {
        if ($this->done($job, self::STEP_ACTIVATE)) {
            return;
        }

        $channel->refresh();

        if ($channel->status !== ChannelStatus::Active) {
            $this->lifecycle->transition(
                $channel,
                ChannelStatus::Active,
                new ProvisioningActor,
                'provisioning completed',
            );
        }

        $channel->refresh();
        $channel->provisioned_at = now();
        $channel->save();

        $this->complete($job, self::STEP_ACTIVATE);
    }

    private function done(ChannelProvisionJob $job, string $step): bool
    {
        $steps = $job->completed_steps ?? [];

        return in_array($step, $steps, true);
    }

    private function complete(ChannelProvisionJob $job, string $step): void
    {
        $steps = $job->completed_steps ?? [];
        if (! in_array($step, $steps, true)) {
            $steps[] = $step;
        }

        $job->update(['completed_steps' => $steps]);
        $job->completed_steps = $steps;
    }

    private function fail(ChannelProvisionJob $job, string $error): void
    {
        $job->update([
            'status' => 'failed',
            'error' => $error,
            'finished_at' => now(),
        ]);
    }
}
