<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Actions;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Models\AppUser;

final class QuotePrices
{
    public function __construct(
        private readonly PricingEngine $engine,
        private readonly CatalogProductLookup $products,
        private readonly RetailerShoppingContext $shopping,
    ) {}

    /**
     * @param  array{lines: list<array{product_id: int, variant_id?: int|null, qty: int}>, zone_id: int}  $data
     * @return array<string, mixed>
     */
    public function __invoke(object $user, array $data): array
    {
        $retailerId = null;
        $channelId = null;

        if ($user instanceof AppUser && $user->kind === AppUserKind::Retailer) {
            $ctx = $this->shopping->for($user);
            $retailerId = $ctx['retailer_id'];
            foreach ($data['lines'] as $line) {
                if (! $this->products->isVisibleToRetailer((int) $line['product_id'], $ctx)) {
                    throw new DomainException(__('pricing.product_not_available'), 'product_not_available', 422, [
                        'product_id' => (int) $line['product_id'],
                    ]);
                }
            }
        }

        unset($data['unit_price']);

        return $this->engine->quote([
            'lines' => $data['lines'],
            'zone_id' => (int) $data['zone_id'],
            'retailer_id' => $retailerId,
            'channel_id' => $channelId,
        ]);
    }
}
