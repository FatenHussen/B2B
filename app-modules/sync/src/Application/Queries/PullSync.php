<?php

declare(strict_types=1);

namespace Modules\Sync\Application\Queries;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\CatalogSyncSource;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Sync\Domain\Models\SyncCursor;

final class PullSync
{
    public function __construct(
        private readonly CatalogSyncSource $catalog,
        private readonly RetailerShoppingContext $shopping,
        private readonly RepSellingContext $selling,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, Request $request): array
    {
        $cursor = $request->query('cursor');
        $cursor = is_string($cursor) ? $cursor : null;
        $limit = min(max((int) $request->query('limit', 200), 1), 500);
        $scopes = $request->query('scopes', ['catalog']);
        if (! is_array($scopes) || $scopes === []) {
            $scopes = ['catalog'];
        }

        $channelIds = $this->channelIds($user);
        $fullResync = $this->tooOld($cursor);
        $changes = [];
        $next = now()->timezone('Asia/Damascus')->toIso8601String();
        $hasMore = false;

        foreach ($scopes as $scope) {
            $scope = (string) $scope;
            if ($scope === 'catalog' && ! $fullResync) {
                $chunk = $this->catalog->changesSince($cursor, $channelIds, $limit);
                $changes[$scope] = [
                    'upserts' => $chunk['upserts'],
                    'deletes' => $chunk['deletes'],
                ];
                $next = $chunk['next_cursor'];
                $hasMore = $chunk['has_more'] || $hasMore;
            } else {
                $changes[$scope] = ['upserts' => [], 'deletes' => []];
            }
        }

        $device = (string) $request->header('X-Device-Id', '');
        if ($device !== '') {
            SyncCursor::query()->updateOrCreate(
                ['device_uuid' => $device],
                [
                    'app_user_id' => (int) $user->getAuthIdentifier(),
                    'cursor' => $next,
                    'pulled_at' => now(),
                ],
            );
        }

        return [
            'changes' => $changes,
            'next_cursor' => $next,
            'has_more' => $hasMore,
            'full_resync_required' => $fullResync,
        ];
    }

    /**
     * @return list<int>
     */
    private function channelIds(object $user): array
    {
        if ($this->shopping->isRetailer($user)) {
            return $this->shopping->for($user)['channel_ids'];
        }
        if ($this->selling->isRep($user)) {
            return $this->selling->for($user)['channel_ids'];
        }

        return [];
    }

    private function tooOld(?string $cursor): bool
    {
        if ($cursor === null || $cursor === '' || str_starts_with($cursor, 'c_')) {
            return false;
        }
        try {
            return Carbon::parse($cursor)->lt(now()->subDays(30));
        } catch (\Throwable) {
            return true;
        }
    }
}
