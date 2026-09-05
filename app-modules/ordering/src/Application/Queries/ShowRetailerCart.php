<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Ordering\Application\Support\CartAssembler;

final class ShowRetailerCart
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly RetailerShoppingContext $shopping,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user): array
    {
        $ctx = $this->shopping->for($user);
        $cart = $this->carts->activeFor($user);
        $this->carts->reprice($cart, $ctx['zone_id'], $ctx['retailer_id']);

        return $this->carts->presentRetailer($cart->fresh(['sections.lines']));
    }
}
