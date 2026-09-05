<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ReceiptNumberReserver;

final class EloquentReceiptNumberReserver implements ReceiptNumberReserver
{
    public function reserve(int $channelId): string
    {
        return DB::transaction(function () use ($channelId): string {
            $row = DB::table('receipt_counters')->where('channel_id', $channelId)->lockForUpdate()->first();
            if ($row === null) {
                DB::table('receipt_counters')->insert(['channel_id' => $channelId, 'next_no' => 2]);

                return 'RCPT-1';
            }

            $n = (int) $row->next_no;
            DB::table('receipt_counters')->where('channel_id', $channelId)->update(['next_no' => $n + 1]);

            return 'RCPT-'.$n;
        });
    }
}
