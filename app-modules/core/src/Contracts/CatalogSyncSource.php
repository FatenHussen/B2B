<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface CatalogSyncSource
{
    /**
     * Product rows changed since `$cursor` (ISO-8601 or empty) for the caller's
     * channels. Sync (Coordination) must not import Catalog models.
     *
     * @param  list<int>  $channelIds
     * @return array{upserts: list<array<string, mixed>>, deletes: list<int>, next_cursor: string, has_more: bool}
     */
    public function changesSince(?string $cursor, array $channelIds, int $limit): array;
}
