<?php

declare(strict_types=1);

namespace Modules\Promotion\Application\Services;

use Illuminate\Support\Carbon;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Models\Offer;
use Modules\Promotion\Domain\Models\OfferRedemption;

/**
 * Resolves time- and qty-driven statuses (DOC §4.4.2).
 * Draft and manually stopped are sticky; everything else is materialised from window + qty.
 */
final class OfferStatusRefresh
{
    public function refresh(Offer $offer): Offer
    {
        $current = $offer->status;
        if ($current === OfferStatus::Draft || $current === OfferStatus::Stopped) {
            return $offer;
        }

        $now = Carbon::now('Asia/Damascus');
        $next = $this->compute($offer, $now);

        if ($next !== $current) {
            $offer->forceFill(['status' => $next])->save();
        }

        return $offer;
    }

    public function resolveOnCreate(OfferStatus $requested, ?string $startsAt): OfferStatus
    {
        if ($requested === OfferStatus::Draft || $requested === OfferStatus::Stopped) {
            return $requested;
        }

        if ($startsAt !== null && Carbon::parse($startsAt, 'Asia/Damascus')->gt(Carbon::now('Asia/Damascus'))) {
            return OfferStatus::Scheduled;
        }

        if ($requested === OfferStatus::Scheduled) {
            return OfferStatus::Scheduled;
        }

        return OfferStatus::Active;
    }

    private function compute(Offer $offer, Carbon $now): OfferStatus
    {
        if ($offer->ends_at !== null && $offer->ends_at->lt($now)) {
            return OfferStatus::Expired;
        }

        if ($this->isExhausted($offer)) {
            return OfferStatus::Exhausted;
        }

        if ($offer->starts_at !== null && $offer->starts_at->gt($now)) {
            return OfferStatus::Scheduled;
        }

        return OfferStatus::Active;
    }

    private function isExhausted(Offer $offer): bool
    {
        if ($offer->total_qty === null) {
            return false;
        }

        $offer->loadMissing('redemption');
        $redemption = $offer->redemption;

        return $redemption instanceof OfferRedemption
            && (int) $redemption->qty_consumed >= (int) $offer->total_qty;
    }
}
