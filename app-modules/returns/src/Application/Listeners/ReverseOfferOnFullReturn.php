<?php

declare(strict_types=1);

namespace Modules\Returns\Application\Listeners;

use Modules\Core\Contracts\OfferConsumption;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Events\ReturnDecided;
use Modules\Returns\Domain\Models\ReturnLine;
use Modules\Returns\Domain\Models\ReturnRequest;

/**
 * DOC §4.4.3 — full return of offer-bearing lines voids the offer application.
 * Lives in Returns (Coordination) and calls Promotion only through OfferConsumption.
 */
final class ReverseOfferOnFullReturn
{
    public function __construct(
        private readonly SubOrderLifecycle $orders,
        private readonly OfferConsumption $offers,
    ) {}

    public function handle(ReturnDecided $event): void
    {
        if ($event->decision !== 'approve') {
            return;
        }

        // Lifted, per rule 10: ReturnDecided carries no tenant; the return request id
        // is the owner key and the offer reverse is scoped by retailer_id beside it.
        $request = ReturnRequest::query()->acrossChannels()->with('lines')->find($event->returnRequestId);
        if ($request === null) {
            return;
        }

        $header = $this->orders->header($event->subOrderId);
        if ($header === null) {
            return;
        }

        $orderLines = collect($this->orders->lines($event->subOrderId))->keyBy('id');
        $returnedQtyByLine = [];
        foreach ($request->lines as $line) {
            /** @var ReturnLine $line */
            $returnedQtyByLine[(int) $line->line_id] = ((int) ($returnedQtyByLine[(int) $line->line_id] ?? 0))
                + (int) $line->qty;
        }

        $offerLineQty = [];
        $offerReturnedQty = [];
        foreach ($orderLines as $lineId => $orderLine) {
            $offerId = $orderLine['offer_id'] ?? null;
            if ($offerId === null) {
                continue;
            }
            $offerId = (int) $offerId;
            $offerLineQty[$offerId] = ($offerLineQty[$offerId] ?? 0) + (int) $orderLine['qty'];
            $offerReturnedQty[$offerId] = ($offerReturnedQty[$offerId] ?? 0)
                + (int) ($returnedQtyByLine[(int) $lineId] ?? 0);
        }

        foreach ($offerLineQty as $offerId => $totalQty) {
            if (($offerReturnedQty[$offerId] ?? 0) >= $totalQty && $totalQty > 0) {
                $this->offers->reverseApplied($offerId, (int) $header['retailer_id'], 1);
            }
        }
    }
}
