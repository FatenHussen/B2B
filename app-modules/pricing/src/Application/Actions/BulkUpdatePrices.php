<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Actions;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\Tenant;
use Modules\Pricing\Domain\Enums\AdjustmentMode;
use Modules\Pricing\Domain\Models\PriceChangeLog;
use Modules\Pricing\Domain\Models\ProductBasePrice;

final class BulkUpdatePrices
{
    public function __construct(
        private readonly CatalogProductLookup $products,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{product_ids: list<int>, mode: string, value: int, reason?: string|null}  $data
     * @return array{affected_count: int}
     */
    public function __invoke(object $actor, array $data): array
    {
        $mode = AdjustmentMode::from((string) $data['mode']);
        $value = (int) $data['value'];
        $channelId = (int) Tenant::currentId();
        $affected = 0;

        foreach ($data['product_ids'] as $productId) {
            $productId = (int) $productId;
            if ($this->products->channelId($productId) !== $channelId) {
                continue;
            }
            $row = ProductBasePrice::query()->where('product_id', $productId)->first();
            if ($row === null) {
                continue;
            }
            $before = (int) $row->base_price;
            $after = match ($mode) {
                AdjustmentMode::Percent => $before + intdiv($before * $value, 100),
                AdjustmentMode::Fixed => $before + $value,
            };
            $row->forceFill(['base_price' => $after])->save();
            PriceChangeLog::query()->create([
                'product_id' => $productId,
                'actor_user_id' => (int) $actor->getAuthIdentifier(),
                'before' => $before,
                'after' => $after,
                'reason' => $data['reason'] ?? null,
                'at' => now(),
            ]);
            $affected++;
        }

        $this->audit->record('pricing.bulk', $actor, 'product', null, [
            'after' => ['affected' => $affected, 'mode' => $mode->value, 'value' => $value],
        ], $channelId);

        return ['affected_count' => $affected];
    }
}
