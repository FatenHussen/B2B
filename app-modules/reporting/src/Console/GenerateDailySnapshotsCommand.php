<?php

declare(strict_types=1);

namespace Modules\Reporting\Console;

use Illuminate\Console\Command;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Reporting\Application\Jobs\GenerateDailySnapshot;

final class GenerateDailySnapshotsCommand extends Command
{
    protected $signature = 'reports:daily-snapshots {--date= : Snapshot date Y-m-d (defaults to yesterday, Asia/Damascus)}';

    protected $description = 'Queue a daily channel snapshot for every supply channel.';

    public function handle(ChannelDirectory $channels): int
    {
        $date = $this->option('date');
        $onDate = is_string($date) && $date !== ''
            ? $date
            : now('Asia/Damascus')->subDay()->toDateString();

        foreach ($channels->allIds() as $channelId) {
            GenerateDailySnapshot::dispatch($channelId, $onDate);
        }

        return self::SUCCESS;
    }
}
