<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Pricing\Domain\Enums\AdjustmentMode;
use Modules\Pricing\Domain\Enums\PriceListStatus;
use Modules\Pricing\Domain\Enums\PriceListType;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Domain\Models\PriceListZone;

final class CreatePriceList
{
    public function __construct(
        private readonly ReferenceDirectory $refs,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int, status: string}
     */
    public function __invoke(object $actor, array $data): array
    {
        $type = PriceListType::from((string) $data['type']);
        $adjustment = $data['adjustment'] ?? [];

        if ($type === PriceListType::Zone && empty($data['zone_ids'])) {
            InvalidFields::throw(['zone_ids' => 'pricing.zone_ids_required']);
        }
        if ($type === PriceListType::Retailer && empty($data['retailer_id'])) {
            InvalidFields::throw(['retailer_id' => 'pricing.retailer_required']);
        }
        if ($type === PriceListType::Group && empty($data['group_id'])) {
            InvalidFields::throw(['group_id' => 'pricing.group_required']);
        }

        $zoneIds = array_map('intval', $data['zone_ids'] ?? []);
        if (! $this->refs->allZonesExist($zoneIds)) {
            InvalidFields::throw(['zone_ids' => 'pricing.zone_not_found']);
        }

        $list = DB::transaction(function () use ($data, $type, $adjustment, $zoneIds, $actor): PriceList {
            $from = $data['effective_from'] ?? null;
            $status = $from && now('Asia/Damascus')->lt($from)
                ? PriceListStatus::Scheduled
                : PriceListStatus::Active;

            $list = PriceList::query()->create([
                'name' => $data['name'],
                'type' => $type,
                'status' => $status,
                'group_id' => $data['group_id'] ?? null,
                'retailer_id' => $data['retailer_id'] ?? null,
                'adjustment_mode' => AdjustmentMode::from((string) ($adjustment['mode'] ?? 'percent')),
                'adjustment_value' => (int) ($adjustment['value'] ?? 0),
                'effective_from' => $from,
                'effective_to' => $data['effective_to'] ?? null,
                'reason' => $data['reason'] ?? null,
            ]);

            foreach ($zoneIds as $zoneId) {
                PriceListZone::query()->create(['price_list_id' => $list->id, 'zone_id' => $zoneId]);
            }

            $this->audit->record('pricing.list.create', $actor, 'price_list', (int) $list->id, [
                'after' => ['name' => $list->name, 'type' => $type->value],
            ], Tenant::currentId());

            return $list;
        });

        return ['id' => (int) $list->id, 'status' => $list->status->value];
    }
}
