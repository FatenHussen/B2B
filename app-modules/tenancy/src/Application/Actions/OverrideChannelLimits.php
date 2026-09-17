<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Contracts\ChannelLimits;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\Tenant;
use Modules\Tenancy\Domain\ChannelLimitResolver;
use Modules\Tenancy\Domain\Models\ChannelLimit;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * BE-T12 — EP-AD-055. Writes an override beside the base limits, never over them.
 *
 * The base stays what the plan gave the channel at provisioning. An override with
 * `temporary_until` reverts by itself when the clock passes it, because the resolver
 * compares on every read; an override without one holds until the next call here
 * replaces it. Keys not named in the body are cleared, so an override is always the whole
 * picture the operator last confirmed, never a merge of two half-remembered calls.
 */
final class OverrideChannelLimits
{
    public function __construct(
        private readonly ChannelLimits $limits,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{limits: array<string, int>, temporary_until?: string|null, reason: string}  $data
     * @return array{limits: array<string, int>, temporary_until: string|null, reason: string}
     */
    public function __invoke(SupplyChannel $channel, array $data, object $actor): array
    {
        $channelId = (int) $channel->id;
        $before = $this->limits->caps($channelId);
        $until = isset($data['temporary_until'])
            ? Carbon::parse((string) $data['temporary_until'])
            : null;

        Tenant::as($channelId, function () use ($channelId, $data, $until, $actor): void {
            $row = ChannelLimit::query()->first();
            if ($row === null) {
                // A channel that was never provisioned with limits gets its base from
                // the defaults so an override has something to stand beside.
                $row = ChannelLimit::query()->create(['channel_id' => $channelId] + ChannelLimitResolver::DEFAULTS);
            }

            $attributes = ['temporary_until' => $until, 'reason' => $data['reason']];
            foreach (ChannelLimits::KEYS as $key) {
                $attributes['override_'.$key] = array_key_exists($key, $data['limits']) ? (int) $data['limits'][$key] : null;
            }
            $attributes['overridden_by'] = method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null;
            $attributes['overridden_at'] = now();

            $row->fill($attributes)->save();
        });

        $after = $this->limits->caps($channelId);

        $this->audit->record('channel.limits.override', $actor, 'supply_channel', $channelId, [
            'before' => $before,
            'after' => $after,
            'temporary_until' => $until?->toIso8601String(),
            'reason' => $data['reason'],
        ], $channelId);

        return [
            'limits' => $after,
            'temporary_until' => $until?->toIso8601String(),
            'reason' => $data['reason'],
        ];
    }
}
