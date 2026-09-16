<?php

declare(strict_types=1);

namespace Modules\Promotion\Application\Queries;

use Modules\Core\Contracts\OfferLineStats;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Promotion\Domain\Models\Offer;
use Modules\Promotion\Domain\Models\OfferRedemption;

final class ShowOfferPerformance
{
    public function __construct(private readonly OfferLineStats $lines) {}

    /**
     * @return array{
     *     applied_count: int,
     *     linked_sales: int,
     *     discount_given: int,
     *     net_margin: int,
     *     retailers_count: int,
     *     by_zone: list<array{zone_id: int, applied_count: int}>,
     *     conversion_rate: int
     * }
     */
    public function __invoke(int $id): array
    {
        $offer = Offer::query()->with('redemption')->find($id);
        if ($offer === null) {
            throw new DomainException(__('promotion.not_found'), 'not_found', 404);
        }

        $stats = $this->lines->forOffer((int) $offer->id, (int) Tenant::currentId());
        $redemption = $offer->redemption;
        $applied = $redemption instanceof OfferRedemption ? (int) $redemption->applied_count : 0;

        // conversion_rate is dimensionless at scale 10^4. Views are not stored, so 0.
        // net_margin needs cost; stay 0 rather than invent a margin.
        return [
            'applied_count' => $applied,
            'linked_sales' => $stats['linked_sales'],
            'discount_given' => $stats['discount_given'],
            'net_margin' => 0,
            'retailers_count' => $stats['retailers_count'],
            'by_zone' => $stats['by_zone'],
            'conversion_rate' => 0,
        ];
    }
}
