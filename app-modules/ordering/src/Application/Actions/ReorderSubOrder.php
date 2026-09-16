<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Application\Support\CartAssembler;
use Modules\Ordering\Application\Support\OpaqueChannelRef;
use Modules\Ordering\Domain\Enums\CartLineSource;
use Modules\Ordering\Domain\Models\CartLine;
use Modules\Ordering\Domain\Models\CartSection;
use Modules\Ordering\Domain\Models\SubOrder;

final class ReorderSubOrder
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly RetailerShoppingContext $shopping,
        private readonly CatalogProductLookup $products,
    ) {}

    /**
     * @return array{cart: array<string, mixed>}
     */
    public function __invoke(object $user, int $id): array
    {
        $ctx = $this->shopping->for($user);
        // acrossChannels(), per rule 10: `/app/retailer/*` sets no tenant. `retailer_id`
        // below is the isolation.
        $sub = SubOrder::query()->acrossChannels()->with('lines')->whereKey($id)->where('retailer_id', $ctx['retailer_id'])->first();
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $cart = $this->carts->activeFor($user);
        foreach ($sub->lines as $line) {
            $channelId = $this->products->channelId((int) $line->product_id);
            if ($channelId === null) {
                continue;
            }
            // acrossChannels(), per rule 10 (BE-C12): the cart is the owner's — `cart_id`.
            $section = CartSection::query()->acrossChannels()->firstOrCreate(
                ['cart_id' => $cart->id, 'channel_id' => $channelId, 'retailer_id' => null],
                ['opaque_ref' => OpaqueChannelRef::make($channelId)],
            );
            CartLine::query()->create([
                'section_id' => $section->id,
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'qty' => $line->qty,
                'source' => CartLineSource::Reorder,
            ]);
        }
        $this->carts->reprice($cart, $ctx['zone_id'], $ctx['retailer_id']);

        return ['cart' => $this->carts->presentRetailer($cart->fresh(['sections.lines']))];
    }
}
