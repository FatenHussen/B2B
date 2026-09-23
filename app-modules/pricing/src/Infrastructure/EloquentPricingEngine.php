<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure;

use Illuminate\Support\Carbon;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\OfferApplicator;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Pricing\Domain\Enums\AdjustmentMode;
use Modules\Pricing\Domain\Enums\PriceListStatus;
use Modules\Pricing\Domain\Enums\PriceListType;
use Modules\Pricing\Domain\Enums\PriceType;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Domain\Models\ProductBasePrice;
use Modules\Pricing\Domain\Models\ProductQtyTier;

final class EloquentPricingEngine implements PricingEngine
{
    public function __construct(
        private readonly CatalogProductLookup $products,
        private readonly ReferenceDirectory $refs,
    ) {}

    public function quote(array $request): array
    {
        $zoneId = (int) $request['zone_id'];
        $retailerId = isset($request['retailer_id']) ? (int) $request['retailer_id'] : null;
        $channelId = isset($request['channel_id']) ? (int) $request['channel_id'] : null;
        $activityTypeId = isset($request['activity_type_id']) ? (int) $request['activity_type_id'] : null;
        /** @var list<int> $groupIds */
        $groupIds = array_map('intval', $request['group_ids'] ?? []);
        $linesOut = [];
        $subtotal = 0;
        $currency = 'SYP';

        foreach ($request['lines'] as $index => $line) {
            $productId = (int) $line['product_id'];
            $qty = (int) $line['qty'];
            $productChannel = $this->products->channelId($productId);

            if ($productChannel === null || ! $this->products->isActive($productId)) {
                throw new DomainException(__('pricing.product_not_available'), 'product_not_available', 422, [
                    'line' => $index,
                    'product_id' => $productId,
                ]);
            }

            if ($channelId !== null && $productChannel !== $channelId) {
                throw new DomainException(__('pricing.product_not_available'), 'product_not_available', 422, [
                    'line' => $index,
                    'product_id' => $productId,
                ]);
            }

            $variantId = isset($line['variant_id']) ? (int) $line['variant_id'] : null;
            $quoted = $this->quoteLine($productId, $qty, $zoneId, $retailerId, $productChannel, $groupIds, $variantId);
            $lineTotal = $quoted['unit_price'] * $qty;
            $subtotal += $lineTotal;
            $currency = $quoted['currency'] ?? $currency;

            $linesOut[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'qty' => $qty,
                'unit_price' => $quoted['unit_price'],
                'applied_rule' => $quoted['applied_rule'],
                'tier' => $quoted['from'] !== null ? ['from' => $quoted['from'], 'to' => $quoted['to']] : null,
                'discount' => 0,
                'line_total' => $lineTotal,
            ];
        }

        $result = [
            'lines' => $linesOut,
            'subtotal' => $subtotal,
            'currency' => $currency,
        ];

        if (app()->bound(OfferApplicator::class)) {
            $result = app(OfferApplicator::class)->apply($result, [
                'zone_id' => $zoneId,
                'retailer_id' => $retailerId,
                'channel_id' => $channelId,
                'activity_type_id' => $activityTypeId,
                'group_ids' => $groupIds,
            ]);
        }

        return $result;
    }

    public function quoteLine(
        int $productId,
        int $qty,
        int $zoneId,
        ?int $retailerId = null,
        ?int $channelId = null,
        array $groupIds = [],
        ?int $variantId = null,
    ): array {
        $channelId ??= $this->products->channelId($productId);
        $base = Tenant::withoutScope(fn () => ProductBasePrice::query()->where('product_id', $productId)->first());

        $override = $this->products->variantPriceOverride($variantId);
        $unit = $override !== null
            ? $override
            : ($base !== null ? (int) $base->base_price : 0);
        $type = $base?->type ?? PriceType::Simple;
        $currencyId = $base !== null ? (int) $base->currency_id : (int) ($this->refs->defaultCurrencyId() ?? 0);
        $currency = $currencyId > 0 ? ($this->refs->currencyCode($currencyId) ?? 'SYP') : 'SYP';

        $applied = $override !== null
            ? ['type' => 'variant_override', 'id' => $variantId, 'label' => '']
            : ['type' => 'base_price', 'id' => $base?->id, 'label' => ''];
        $from = null;
        $to = null;

        // Catalog precedence: base → qty_tier → zone → group → retailer (last wins).
        // Variant override replaces base only; tiers still apply on top when type is tiered
        // unless we treat override as absolute — plan: override is the unit base before lists.
        // When override is set, skip product qty tiers (override is the sell price base).
        if ($override === null && $type === PriceType::Tiered) {
            $tier = Tenant::withoutScope(fn () => ProductQtyTier::query()
                ->where('product_id', $productId)
                ->where('from_qty', '<=', $qty)
                ->where(function ($q) use ($qty): void {
                    $q->whereNull('to_qty')->orWhere('to_qty', '>=', $qty);
                })
                ->orderByDesc('from_qty')
                ->first());

            if ($tier !== null) {
                $unit = (int) $tier->price;
                $from = (int) $tier->from_qty;
                $to = $tier->to_qty !== null ? (int) $tier->to_qty : null;
                $applied = [
                    'type' => 'qty_tier',
                    'id' => (int) $tier->id,
                    'label' => $to === null
                        ? __('pricing.tier_open', ['from' => $from])
                        : __('pricing.tier_closed', ['from' => $from, 'to' => $to]),
                ];
            }
        }

        if ($channelId !== null) {
            $listQuote = $this->applyLists($unit, $productId, $channelId, $zoneId, $retailerId, $groupIds);
            $unit = $listQuote['unit'];
            if ($listQuote['rule'] !== null) {
                $applied = $listQuote['rule'];
            }
        }

        $label = $type === PriceType::Tiered && $override === null
            ? __('pricing.qty_based')
            : (string) $unit;

        return [
            'unit_price' => $unit,
            'type' => $type->value,
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'applied_rule' => $applied,
            'currency' => $currency,
        ];
    }

    /**
     * @param  list<int>  $groupIds
     * @return array{unit: int, rule: array{type: string, id: int|null, label: string}|null}
     */
    private function applyLists(
        int $unit,
        int $productId,
        int $channelId,
        int $zoneId,
        ?int $retailerId,
        array $groupIds,
    ): array {
        $rule = null;
        $now = Carbon::now('Asia/Damascus');

        $apply = function (PriceListType $type) use (
            &$unit,
            &$rule,
            $productId,
            $channelId,
            $zoneId,
            $retailerId,
            $groupIds,
            $now,
        ): void {
            // Lifted, per rule 10: the engine prices for app callers with no tenant, and
            // `where('supply_channel_id', $channelId)` below is the channel the caller named.
            $query = PriceList::withoutGlobalScope('channel')
                ->where('supply_channel_id', $channelId)
                ->where('type', $type)
                ->where('status', PriceListStatus::Active)
                ->where(function ($q) use ($now): void {
                    $q->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
                })
                ->where(function ($q) use ($now): void {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $now);
                });

            if ($type === PriceListType::Zone) {
                $query->whereHas('zones', fn ($z) => $z->where('zone_id', $zoneId));
            }
            if ($type === PriceListType::Group) {
                if ($groupIds === []) {
                    return;
                }
                $query->whereIn('group_id', $groupIds);
            }
            if ($type === PriceListType::Retailer) {
                if ($retailerId === null) {
                    return;
                }
                $query->where('retailer_id', $retailerId);
            }

            $list = $query->orderByDesc('id')->first();
            if ($list === null) {
                return;
            }

            $item = $list->items()->where('product_id', $productId)->first();
            $hasGeneral = $item === null && $list->items()->doesntExist();
            $hasItem = $item !== null;

            if (! $hasItem && ! $hasGeneral) {
                return;
            }

            if ($hasItem && $item->override_price !== null) {
                $unit = (int) $item->override_price;
            } else {
                $unit = $this->adjust($unit, $list->adjustment_mode, (int) $list->adjustment_value);
            }

            $rule = [
                'type' => $type->value.'_list',
                'id' => (int) $list->id,
                'label' => (string) $list->name,
            ];
        };

        // Catalog: retailer_price ← group_list ← zone_list ← qty_tier ← base_price
        $apply(PriceListType::Zone);
        $apply(PriceListType::Group);
        $apply(PriceListType::Retailer);

        return ['unit' => $unit, 'rule' => $rule];
    }

    private function adjust(int $unit, AdjustmentMode $mode, int $value): int
    {
        return match ($mode) {
            AdjustmentMode::Percent => $unit + intdiv($unit * $value, 100),
            AdjustmentMode::Fixed => $unit + $value,
        };
    }
}
