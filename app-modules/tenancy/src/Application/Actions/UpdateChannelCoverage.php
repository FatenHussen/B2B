<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Modules\Core\Contracts\ChannelCoverageWriter;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Tenancy\Domain\Models\SupplyChannel;

final class UpdateChannelCoverage
{
    public function __construct(
        private readonly ChannelCoverageWriter $coverage,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int}
     */
    public function __invoke(SupplyChannel $channel, array $data, object $actor): array
    {
        $zoneIds = array_map('intval', (array) ($data['zone_ids'] ?? []));
        $this->coverage->replace((int) $channel->id, $zoneIds);

        $this->audit->record(
            action: 'channel.coverage.updated',
            actor: $actor,
            subjectType: SupplyChannel::class,
            subjectId: (int) $channel->id,
            properties: [
                'reason' => (string) $data['reason'],
                'zone_ids' => $zoneIds,
            ],
            channelId: (int) $channel->id,
        );

        return ['id' => (int) $channel->id];
    }
}
