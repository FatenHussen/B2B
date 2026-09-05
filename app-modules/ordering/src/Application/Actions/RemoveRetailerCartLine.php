<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Application\Support\CartAssembler;
use Modules\Ordering\Domain\Models\CartLine;

final class RemoveRetailerCartLine
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly RetailerShoppingContext $shopping,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $lineId): array
    {
        $ctx = $this->shopping->for($user);
        $cart = $this->carts->activeFor($user);
        $line = CartLine::query()
            ->whereKey($lineId)
            ->whereHas('section', fn ($q) => $q->where('cart_id', $cart->id))
            ->first();
        if ($line === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        if ($line->offer_id) {
            CartLine::query()
                ->whereHas('section', fn ($q) => $q->where('cart_id', $cart->id))
                ->where('offer_id', $line->offer_id)
                ->delete();
        } else {
            $line->delete();
        }

        foreach ($cart->sections as $section) {
            if ($section->lines()->count() === 0) {
                $section->delete();
            }
        }

        $this->carts->reprice($cart, $ctx['zone_id'], $ctx['retailer_id']);

        return $this->carts->presentRetailer($cart->fresh(['sections.lines']));
    }
}
