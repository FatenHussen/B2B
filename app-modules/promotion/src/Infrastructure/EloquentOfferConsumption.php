<?php

declare(strict_types=1);

namespace Modules\Promotion\Infrastructure;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\OfferConsumption;
use Modules\Promotion\Application\Services\OfferStatusRefresh;
use Modules\Promotion\Domain\Models\Offer;
use Modules\Promotion\Domain\Models\OfferRedemption;
use Modules\Promotion\Domain\Models\OfferRetailerRedemption;
use Modules\Promotion\Domain\Models\OfferView;

final class EloquentOfferConsumption implements OfferConsumption
{
    public function __construct(private readonly OfferStatusRefresh $refresh) {}

    public function recordApplied(int $offerId, int $retailerId, int $qty = 1): void
    {
        if ($qty < 1) {
            return;
        }

        DB::transaction(function () use ($offerId, $retailerId, $qty): void {
            $offer = Offer::withoutGlobalScope('channel')->whereKey($offerId)->lockForUpdate()->first(); // Lifted, per rule 10: redemption is keyed by offer_id from the submitted order line.
            if ($offer === null) {
                return;
            }

            $global = OfferRedemption::query()->firstOrCreate(
                ['offer_id' => $offerId],
                ['applied_count' => 0, 'qty_consumed' => 0],
            );
            $global->forceFill([
                'applied_count' => (int) $global->applied_count + 1,
                'qty_consumed' => (int) $global->qty_consumed + $qty,
            ])->save();

            $per = OfferRetailerRedemption::query()->firstOrCreate(
                ['offer_id' => $offerId, 'retailer_id' => $retailerId],
                ['applied_count' => 0, 'qty_consumed' => 0],
            );
            $per->forceFill([
                'applied_count' => (int) $per->applied_count + 1,
                'qty_consumed' => (int) $per->qty_consumed + $qty,
            ])->save();

            $offer->setRelation('redemption', $global->fresh());
            $this->refresh->refresh($offer);
        });
    }

    public function reverseApplied(int $offerId, int $retailerId, int $qty = 1): void
    {
        if ($qty < 1) {
            return;
        }

        DB::transaction(function () use ($offerId, $retailerId, $qty): void {
            $offer = Offer::withoutGlobalScope('channel')->whereKey($offerId)->lockForUpdate()->first(); // Lifted, per rule 10: redemption is keyed by offer_id from the submitted order line.
            if ($offer === null) {
                return;
            }

            $global = OfferRedemption::query()->where('offer_id', $offerId)->lockForUpdate()->first();
            if ($global !== null) {
                $global->forceFill([
                    'applied_count' => max(0, (int) $global->applied_count - 1),
                    'qty_consumed' => max(0, (int) $global->qty_consumed - $qty),
                ])->save();
            }

            $per = OfferRetailerRedemption::query()
                ->where('offer_id', $offerId)
                ->where('retailer_id', $retailerId)
                ->lockForUpdate()
                ->first();
            if ($per !== null) {
                $per->forceFill([
                    'applied_count' => max(0, (int) $per->applied_count - 1),
                    'qty_consumed' => max(0, (int) $per->qty_consumed - $qty),
                ])->save();
            }

            if ($global !== null) {
                $offer->setRelation('redemption', $global);
            }
            $this->refresh->refresh($offer);
        });
    }

    public function recordView(int $offerId, int $retailerId): void
    {
        OfferView::query()->firstOrCreate(
            ['offer_id' => $offerId, 'retailer_id' => $retailerId],
            ['viewed_at' => now('Asia/Damascus')],
        );
    }

    public function uniqueViewers(int $offerId): int
    {
        return OfferView::query()->where('offer_id', $offerId)->count();
    }
}
