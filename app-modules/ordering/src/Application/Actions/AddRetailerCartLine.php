<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Ordering\Application\Support\CartAssembler;
use Modules\Ordering\Application\Support\OpaqueChannelRef;
use Modules\Ordering\Domain\Enums\CartLineSource;
use Modules\Ordering\Domain\Models\CartLine;
use Modules\Ordering\Domain\Models\CartSection;

final class AddRetailerCartLine
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly CatalogProductLookup $products,
        private readonly RetailerShoppingContext $shopping,
    ) {}

    /**
     * @param  array{product_id: int, variant_id?: int|null, qty: int, source?: string}  $data
     * @return array<string, mixed>
     */
    public function __invoke(object $user, array $data): array
    {
        $ctx = $this->shopping->for($user);
        $productId = (int) $data['product_id'];
        if (! $this->products->isVisibleToRetailer($productId, $ctx)) {
            throw new DomainException(__('ordering.product_not_visible'), 'not_found', 404);
        }
        $channelId = $this->products->channelId($productId);
        if ($channelId === null) {
            InvalidFields::throw(['product_id' => 'ordering.product_not_visible']);
        }

        $cart = $this->carts->activeFor($user);
        // acrossChannels(), per rule 10 (BE-C12): `/app/retailer/*` sets no tenant, and the
        // cart is the owner's — `cart_id` is the isolation, `channel_id` the split key.
        $section = CartSection::query()->acrossChannels()->firstOrCreate(
            ['cart_id' => $cart->id, 'channel_id' => $channelId, 'retailer_id' => null],
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
                'source' => $data['source'] ?? CartLineSource::Browse->value,
            ]);
        }

        $this->carts->reprice($cart, $ctx['zone_id'], $ctx['retailer_id']);

        return $this->carts->presentRetailer($cart->fresh(['sections.lines']));
    }
}
