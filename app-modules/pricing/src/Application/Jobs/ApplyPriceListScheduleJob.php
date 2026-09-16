<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Contracts\PricingDraft;
use Modules\Core\Contracts\ProductPricingWriter;
use Modules\Core\Support\Tenant;
use Modules\Pricing\Domain\Enums\PriceListStatus;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Domain\Models\PriceListSchedule;
use Modules\Pricing\Domain\Models\ProductBasePrice;

final class ApplyPriceListScheduleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly int $priceListId,
    ) {}

    public function handle(ProductPricingWriter $writer): void
    {
        $schedule = PriceListSchedule::query()->where('job_id', $this->jobId)->first();
        // Lifted, per rule 10: a queued job runs with no tenant; the id names one row and
        // came from the tenant that scheduled it.
        $list = PriceList::withoutGlobalScope('channel')->find($this->priceListId);
        if ($schedule === null || $list === null) {
            return;
        }

        Tenant::as((int) $list->supply_channel_id, function () use ($schedule, $list, $writer): void {
            foreach ($schedule->payload['changes'] ?? [] as $change) {
                $productId = (int) $change['product_id'];
                $base = ProductBasePrice::query()->where('product_id', $productId)->first();
                $writer->replace((int) $list->supply_channel_id, $productId, new PricingDraft(
                    $base?->type->value ?? 'simple',
                    (int) $change['base_price'],
                    $base !== null ? (int) $base->currency_id : 0,
                    [],
                ));
            }
            $list->forceFill(['status' => PriceListStatus::Active])->save();
            $schedule->forceFill(['applied_at' => now()])->save();
        });
    }
}
