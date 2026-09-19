<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Modules\Core\Contracts\RepCollectedToday;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\WalletTransaction;

final class EloquentRepCollectedToday implements RepCollectedToday
{
    public function __construct(private readonly RepDirectory $reps) {}

    public function amount(int $repUserId): int
    {
        $channelId = $this->reps->channelIdForUser($repUserId);
        if ($channelId === null) {
            return 0;
        }

        $start = now('Asia/Damascus')->startOfDay()->timezone('UTC');
        $end = now('Asia/Damascus')->endOfDay()->timezone('UTC');

        return Tenant::as($channelId, fn () => (int) WalletTransaction::query()
            ->where('rep_id', $repUserId)
            ->where('type', 'collected')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount'));
    }
}
