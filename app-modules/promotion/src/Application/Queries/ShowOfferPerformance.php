<?php

declare(strict_types=1);

namespace Modules\Promotion\Application\Queries;

use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Promotion\Domain\Models\Offer;

final class ShowOfferPerformance
{
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

        // applied_count is the redemption counter this module owns. linked_sales,
        // discount_given, net_margin, retailers_count, by_zone and conversion_rate
        // need order lines (BE2-PRM05) via a Core contract — Promotion must not query
        // Ordering tables. Until that contract exists those keys stay 0, never invented.
        return [
            'applied_count' => (int) ($offer->redemption?->applied_count ?? 0),
            'linked_sales' => 0,
            'discount_given' => 0,
            'net_margin' => 0,
            'retailers_count' => 0,
            'by_zone' => [],
            'conversion_rate' => 0,
        ];
    }
}
