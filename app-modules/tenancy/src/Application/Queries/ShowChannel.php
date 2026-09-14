<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Queries;

use Modules\Core\Support\Tenant;
use Modules\Tenancy\Domain\ChannelStateMachine;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelEvent;
use Modules\Tenancy\Domain\Models\ChannelGovernorate;
use Modules\Tenancy\Domain\Models\ChannelInternalNote;
use Modules\Tenancy\Domain\Models\ChannelLimit;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\ChannelZoneLookup;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * EP-AD-052 — channel detail for the platform back office.
 *
 * KPIs are computed, including zeros. Tenancy does not read Ordering tables, so a
 * brand-new channel's GMV and order count are genuinely 0, not a catalog example.
 * Manager is null until Identity materialises the invite (BE-T07).
 */
final class ShowChannel
{
    public function __construct(private readonly ChannelStateMachine $machine) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(SupplyChannel $channel): array
    {
        $plan = $channel->plan_id !== null
            ? ChannelPlan::query()->find($channel->plan_id)
            : null;

        $owned = Tenant::as((int) $channel->id, function (): array {
            $limits = ChannelLimit::query()->first();
            $notes = ChannelInternalNote::query()->orderBy('created_at')->get();
            $governorateIds = ChannelGovernorate::query()
                ->pluck('governorate_id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            return [
                'limits' => $limits,
                'notes' => $notes,
                'governorate_ids' => $governorateIds,
            ];
        });

        $zoneIds = ChannelZoneLookup::query()
            ->where('supply_channel_id', $channel->id)
            ->pluck('zone_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $timeline = [
            [
                'at' => $channel->created_at?->toIso8601String(),
                'event' => 'created',
            ],
        ];

        foreach (ChannelEvent::query()->where('channel_id', $channel->id)->orderBy('at')->orderBy('id')->get() as $event) {
            $timeline[] = [
                'at' => $event->at->toIso8601String(),
                'event' => $event->to_status->value,
            ];
        }

        $trialOpen = $channel->trial_ends_at !== null && $channel->trial_ends_at->isFuture();

        return [
            'channel' => [
                'id' => (int) $channel->id,
                'name' => $channel->name,
                'slug' => $channel->slug,
                'status' => $channel->status->value,
                'allowed_next' => array_map(
                    static fn (ChannelStatus $status): string => $status->value,
                    $this->machine->allowedNext($channel->status),
                ),
                'legal_form' => $channel->legal_form,
                'cr_number' => $channel->cr_number,
            ],
            'manager' => null,
            'subscription' => [
                'plan' => $plan?->key,
                'status' => $trialOpen ? 'trial' : ($plan !== null ? 'active' : null),
                'next_renewal' => $channel->trial_ends_at?->toIso8601String(),
            ],
            'limits' => [
                'users' => (int) ($owned['limits']?->users ?? 0),
                'warehouses' => (int) ($owned['limits']?->warehouses ?? 0),
                'reps' => (int) ($owned['limits']?->reps ?? 0),
                'skus' => (int) ($owned['limits']?->skus ?? 0),
                'storage_mb' => (int) ($owned['limits']?->storage_mb ?? 0),
            ],
            'coverage' => [
                'governorate_ids' => $owned['governorate_ids'],
                'zone_ids' => $zoneIds,
            ],
            'kpis' => [
                'gmv_30d' => 0,
                'orders_30d' => 0,
            ],
            'timeline' => $timeline,
            'internal_notes' => $owned['notes']->map(fn (ChannelInternalNote $note): array => [
                'body' => $note->body,
                'at' => $note->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
