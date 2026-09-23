<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure;

use Modules\Core\Contracts\PricingDraft;
use Modules\Core\Contracts\ProductPricingWriter;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Pricing\Domain\Enums\PriceType;
use Modules\Pricing\Domain\Models\ProductBasePrice;
use Modules\Pricing\Domain\Models\ProductQtyTier;

final class EloquentProductPricingWriter implements ProductPricingWriter
{
    public function __construct(private readonly ReferenceDirectory $refs) {}

    public function replace(int $channelId, int $productId, PricingDraft $draft): void
    {
        $type = PriceType::tryFrom($draft->type) ?? PriceType::Simple;

        if ($draft->basePrice < 0) {
            InvalidFields::throw(['pricing.base_price' => 'pricing.invalid_base_price']);
        }

        $currencyId = $draft->currencyId > 0 ? $draft->currencyId : (int) ($this->refs->defaultCurrencyId() ?? 0);
        if ($currencyId < 1 || ! $this->refs->currencyExists($currencyId)) {
            InvalidFields::throw(['pricing.currency_id' => 'pricing.currency_not_found']);
        }

        $tiers = $this->normalizedTiers($draft, $type);

        Tenant::as($channelId, function () use ($channelId, $productId, $type, $currencyId, $draft, $tiers): void {
            ProductBasePrice::query()->updateOrCreate(
                ['product_id' => $productId],
                [
                    'supply_channel_id' => $channelId,
                    'currency_id' => $currencyId,
                    'type' => $type,
                    'base_price' => $draft->basePrice,
                    'tax_percent' => $draft->taxPercent,
                ],
            );

            ProductQtyTier::query()->where('product_id', $productId)->delete();
            foreach ($tiers as $tier) {
                ProductQtyTier::query()->create([
                    'supply_channel_id' => $channelId,
                    'product_id' => $productId,
                    'from_qty' => $tier['from'],
                    'to_qty' => $tier['to'],
                    'price' => $tier['price'],
                ]);
            }
        });
    }

    /**
     * @return list<array{from: int, to: int|null, price: int}>
     */
    private function normalizedTiers(PricingDraft $draft, PriceType $type): array
    {
        if ($type !== PriceType::Tiered) {
            return [];
        }

        $tiers = $draft->tiers;
        usort($tiers, fn (array $a, array $b): int => $a['from'] <=> $b['from']);

        $previousTo = 0;
        foreach ($tiers as $tier) {
            if ($tier['from'] < 1) {
                InvalidFields::throw(['pricing.tiers' => 'pricing.tier_from_qty']);
            }
            if ($tier['price'] < 0) {
                InvalidFields::throw(['pricing.tiers' => 'pricing.invalid_base_price']);
            }
            if ($tier['to'] !== null && $tier['to'] < $tier['from']) {
                InvalidFields::throw(['pricing.tiers' => 'pricing.tier_range']);
            }
            if ($previousTo > 0 && $tier['from'] <= $previousTo) {
                InvalidFields::throw(['pricing.tiers' => 'pricing.tier_overlap']);
            }
            $previousTo = $tier['to'] ?? PHP_INT_MAX;
        }

        return $tiers;
    }
}
