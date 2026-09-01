<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Actions;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Pricing\Domain\Models\RepCommercialLimit;

final class SetRepDiscountCap
{
    public function __construct(
        private readonly RepDirectory $reps,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{max_discount_percent: int, max_cash_hold: int}  $data
     * @return array{rep: array{id: int, max_discount_percent: int, max_cash_hold: int}}
     */
    public function __invoke(object $actor, int $repId, array $data): array
    {
        $channelId = (int) Tenant::currentId();
        if (! $this->reps->belongsToChannel($repId, $channelId)) {
            InvalidFields::throw(['id' => 'pricing.rep_not_in_channel']);
        }

        $row = RepCommercialLimit::query()->updateOrCreate(
            ['channel_id' => $channelId, 'rep_id' => $repId],
            [
                'max_discount_percent' => (int) $data['max_discount_percent'],
                'max_cash_hold' => (int) $data['max_cash_hold'],
            ],
        );

        $this->audit->record('pricing.rep.cap', $actor, 'rep', $repId, [
            'after' => $data,
        ], $channelId);

        return [
            'rep' => [
                'id' => $repId,
                'max_discount_percent' => (int) $row->max_discount_percent,
                'max_cash_hold' => (int) $row->max_cash_hold,
            ],
        ];
    }
}
