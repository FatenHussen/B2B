<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Application\Support\CartAssembler;
use Modules\Ordering\Application\Support\OpaqueChannelRef;
use Modules\Ordering\Domain\Enums\CartLineSource;
use Modules\Ordering\Domain\Models\CartLine;
use Modules\Ordering\Domain\Models\CartSection;

final class AddRepCartLine
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly CatalogProductLookup $products,
        private readonly PricingEngine $pricing,
        private readonly RepSellingContext $selling,
        private readonly RetailerDirectory $retailers,
    ) {}

    /**
     * @param  array{retailer_id: int, product_id: int, variant_id?: int|null, qty: int}  $data
     * @return array<string, mixed>
     */
    public function __invoke(object $user, array $data): array
    {
        $selling = $this->selling->for($user);
        $retailerId = (int) $data['retailer_id'];
        if (! $this->retailers->exists($retailerId)) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $productId = (int) $data['product_id'];
        $channelId = $this->products->channelId($productId);
        if ($channelId === null || ! in_array($channelId, $selling['channel_ids'], true)) {
            throw new DomainException(__('ordering.product_not_visible'), 'not_found', 404);
        }

        $cart = $this->carts->activeFor($user);
        $section = CartSection::query()->firstOrCreate(
            ['cart_id' => $cart->id, 'channel_id' => $channelId, 'retailer_id' => $retailerId],
            ['opaque_ref' => OpaqueChannelRef::make($channelId)],
        );

        $variantId = isset($data['variant_id']) ? (int) $data['variant_id'] : null;
        $line = CartLine::query()
            ->where('section_id', $section->id)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();
        $qty = (int) $data['qty'];
        if ($line !== null) {
            $line->qty = (int) $line->qty + $qty;
            $line->save();
        } else {
            CartLine::query()->create([
                'section_id' => $section->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'qty' => $qty,
                'source' => CartLineSource::Browse,
            ]);
        }

        $this->carts->reprice($cart, (int) $this->retailers->zoneId($retailerId), $retailerId, $channelId);

        return $this->carts->presentRep($cart->fresh(['sections.lines']));
    }
}
