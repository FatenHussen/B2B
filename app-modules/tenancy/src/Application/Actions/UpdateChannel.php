<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\Tenant;
use Modules\Tenancy\Domain\Models\ChannelActivityType;
use Modules\Tenancy\Domain\Models\ChannelInternalNote;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * EP-AD-062 — update a channel profile. BR-AD-11: a written reason is required
 * and the write is audited. Status is not a field (rule 8).
 */
final class UpdateChannel
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int}
     */
    public function __invoke(SupplyChannel $channel, array $data, object $actor): array
    {
        $reason = (string) $data['reason'];
        $before = [
            'name' => $channel->name,
            'legal_form' => $channel->legal_form,
            'cr_number' => $channel->cr_number,
        ];

        $channel->fill([
            'name' => $data['name'] ?? $channel->name,
            'legal_form' => array_key_exists('legal_form', $data) ? $data['legal_form'] : $channel->legal_form,
            'cr_number' => array_key_exists('cr_number', $data) ? $data['cr_number'] : $channel->cr_number,
        ]);
        $channel->save();

        Tenant::as((int) $channel->id, function () use ($channel, $data, $actor): void {
            if (array_key_exists('activity_type_ids', $data) && is_array($data['activity_type_ids'])) {
                $wanted = array_values(array_unique(array_map(intval(...), $data['activity_type_ids'])));
                ChannelActivityType::query()->whereNotIn('activity_type_id', $wanted === [] ? [0] : $wanted)->delete();
                foreach ($wanted as $activityTypeId) {
                    ChannelActivityType::query()->firstOrCreate([
                        'channel_id' => $channel->id,
                        'activity_type_id' => $activityTypeId,
                    ]);
                }
            }

            if (! empty($data['internal_note'])) {
                ChannelInternalNote::query()->create([
                    'channel_id' => $channel->id,
                    'body' => (string) $data['internal_note'],
                    'actor_type' => $actor::class,
                    'actor_id' => method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null,
                    'created_at' => now(),
                ]);
            }
        });

        $this->audit->record(
            'channel.update',
            $actor,
            'supply_channel',
            (int) $channel->id,
            [
                'before' => $before,
                'after' => [
                    'name' => $channel->name,
                    'legal_form' => $channel->legal_form,
                    'cr_number' => $channel->cr_number,
                ],
                'reason' => $reason,
            ],
            (int) $channel->id,
        );

        return ['id' => (int) $channel->id];
    }
}
