<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Tenancy\Application\Jobs\ProvisionChannel;
use Modules\Tenancy\Domain\Models\ChannelActivityType;
use Modules\Tenancy\Domain\Models\ChannelDocument;
use Modules\Tenancy\Domain\Models\ChannelGovernorate;
use Modules\Tenancy\Domain\Models\ChannelInternalNote;
use Modules\Tenancy\Domain\Models\ChannelLimit;
use Modules\Tenancy\Domain\Models\ChannelProvisionJob;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * EP-AD-051 — create a channel and queue its provisioning.
 *
 * Writes the rows that belong to the create request (channel, limits, coverage
 * governorates, activity types, documents, internal note, provision job), then
 * dispatches an empty `ProvisionChannel` on the `provisioning` queue. The job body
 * and the move to `active` are BE-T05. Zone coverage (`channel_zone`) and the manager
 * invite stay in the job payload until then.
 *
 * Status is never assigned here (rule 8): the column default is `provisioning`.
 */
final class CreateChannel
{
    /**
     * @param  array<string, mixed>  $data  validated EP-AD-051 body
     * @param  object|null  $actor  authenticated platform user, when present
     * @return array{id: int, status: string, provisioning_job_id: string}
     */
    public function __invoke(array $data, ?object $actor = null): array
    {
        $publicId = 'job_prov_'.Str::lower((string) Str::ulid());

        $channel = DB::transaction(function () use ($data, $actor, $publicId): SupplyChannel {
            $trialDays = (int) ($data['trial_days'] ?? 0);

            $channel = SupplyChannel::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'legal_form' => $data['legal_form'],
                'cr_number' => $data['cr_number'],
                'logo_media_id' => $data['logo'] ?? null,
                'plan_id' => $data['plan_id'],
                'billing_cycle' => $data['billing_cycle'],
                'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
                'custom_discount' => (int) ($data['custom_discount'] ?? 0),
            ]);

            $limits = $data['limits'];
            ChannelLimit::query()->create([
                'channel_id' => $channel->id,
                'users' => (int) $limits['users'],
                'warehouses' => (int) $limits['warehouses'],
                'reps' => (int) $limits['reps'],
                'skus' => (int) $limits['skus'],
                'storage_mb' => (int) $limits['storage_mb'],
            ]);

            foreach ($data['governorate_ids'] as $governorateId) {
                ChannelGovernorate::query()->create([
                    'channel_id' => $channel->id,
                    'governorate_id' => (int) $governorateId,
                ]);
            }

            foreach ($data['activity_type_ids'] as $activityTypeId) {
                ChannelActivityType::query()->create([
                    'channel_id' => $channel->id,
                    'activity_type_id' => (int) $activityTypeId,
                ]);
            }

            foreach ($data['documents'] ?? [] as $mediaId) {
                ChannelDocument::query()->create([
                    'channel_id' => $channel->id,
                    'media_id' => (int) $mediaId,
                ]);
            }

            if (! empty($data['internal_note'])) {
                ChannelInternalNote::query()->create([
                    'channel_id' => $channel->id,
                    'body' => (string) $data['internal_note'],
                    'actor_type' => $actor !== null ? $actor::class : null,
                    'actor_id' => $actor !== null && isset($actor->id) ? (int) $actor->id : null,
                    'created_at' => now(),
                ]);
            }

            // Payload carries everything BE-T05 materialises (manager, zones, …) so a
            // retry needs nothing from the request that started it.
            ChannelProvisionJob::query()->create([
                'public_id' => $publicId,
                'channel_id' => $channel->id,
                'status' => 'queued',
                'payload' => $data,
                'completed_steps' => [],
            ]);

            return $channel;
        });

        ProvisionChannel::dispatch($publicId)->onQueue('provisioning');

        return [
            'id' => (int) $channel->id,
            'status' => $channel->status->value,
            'provisioning_job_id' => $publicId,
        ];
    }
}
