<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure;

use Modules\Core\Contracts\ProductPricingReader;
use Modules\Core\Support\Tenant;
use Modules\Pricing\Domain\Models\ProductBasePrice;
use Modules\Pricing\Domain\Models\ProductQtyTier;

final class EloquentProductPricingReader implements ProductPricingReader
{
    public function show(int $productId): ?array
    {
        $base = Tenant::withoutScope(
            fn () => ProductBasePrice::query()->where('product_id', $productId)->first()
        );

        if ($base === null) {
            return null;
        }

        $tiers = Tenant::withoutScope(
            fn () => ProductQtyTier::query()
                ->where('product_id', $productId)
                ->orderBy('from_qty')
                ->get()
                ->map(fn (ProductQtyTier $tier) => [
                    'from' => (int) $tier->from_qty,
                    'to' => $tier->to_qty !== null ? (int) $tier->to_qty : null,
                    'price' => (int) $tier->price,
                ])
                ->all()
        );

        return [
            'type' => $base->type->value,
            'base_price' => (int) $base->base_price,
            'currency_id' => (int) $base->currency_id,
            'cost_price' => $base->cost_price !== null ? (int) $base->cost_price : null,
            'tax_percent' => (int) ($base->tax_percent ?? 0),
            'tiers' => $tiers,
        ];
    }

    public function costPrice(int $productId): ?int
    {
        $cost = Tenant::withoutScope(
            fn () => ProductBasePrice::query()->where('product_id', $productId)->value('cost_price')
        );

        return $cost !== null ? (int) $cost : null;
    }
}
