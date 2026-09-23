<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure;

use Illuminate\Support\Carbon;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Contracts\CatalogSyncSource;

final class EloquentCatalogSyncSource implements CatalogSyncSource
{
    public function changesSince(?string $cursor, array $channelIds, int $limit): array
    {
        if ($channelIds === []) {
            return ['upserts' => [], 'deletes' => [], 'next_cursor' => now()->toIso8601String(), 'has_more' => false];
        }

        $since = $this->parseCursor($cursor);
        // Lifted, per rule 10: `/app/sync/pull` sets no tenant; whereIn channel ids is the caller.
        $query = Product::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $channelIds)
            ->when($since !== null, fn ($q) => $q->where('updated_at', '>', $since))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit + 1);

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $slice = $hasMore ? $rows->take($limit) : $rows;
        $last = $slice->last();

        return [
            'upserts' => $slice->map(fn (Product $p) => [
                'id' => (int) $p->id,
                'sku' => $p->sku,
                'name' => $p->name_ar,
                'channel_id' => (int) $p->supply_channel_id,
                'updated_at' => $p->updated_at?->timezone('Asia/Damascus')->toIso8601String(),
            ])->all(),
            'deletes' => [],
            'next_cursor' => $last?->updated_at?->timezone('Asia/Damascus')->toIso8601String()
                ?? now()->timezone('Asia/Damascus')->toIso8601String(),
            'has_more' => $hasMore,
        ];
    }

    private function parseCursor(?string $cursor): ?\DateTimeInterface
    {
        if ($cursor === null || $cursor === '' || str_starts_with($cursor, 'c_')) {
            return null;
        }

        try {
            return Carbon::parse($cursor);
        } catch (\Throwable) {
            return null;
        }
    }
}
