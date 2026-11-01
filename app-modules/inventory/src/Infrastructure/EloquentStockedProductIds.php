<?php

declare(strict_types=1);

namespace Modules\Inventory\Infrastructure;

use Modules\Core\Contracts\StockedProductIds;
use Modules\Core\Support\Tenant;
use Modules\Inventory\Domain\Models\StockBalance;

final class EloquentStockedProductIds implements StockedProductIds
{
    public function ids(array $channelIds): array
    {
        $ids = [];
        foreach ($channelIds as $channelId) {
            if ($channelId <= 0) {
                continue;
            }
            $found = Tenant::as($channelId, function (): array {
                return StockBalance::query()
                    ->get()
                    ->groupBy(fn (StockBalance $row): int => (int) $row->product_id)
                    ->filter(fn ($rows): bool => $rows->sum(fn (StockBalance $row): int => $row->available()) > 0)
                    ->keys()
                    ->map(fn ($id): int => (int) $id)
                    ->all();
            });
            array_push($ids, ...$found);
        }

        return array_values(array_unique($ids));
    }
}
