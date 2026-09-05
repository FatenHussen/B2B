<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Application\Support\CartAssembler;
use Modules\Ordering\Domain\Models\CartLine;

final class UpdateRetailerCartLine
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly RetailerShoppingContext $shopping,
    ) {}

    /**
     * @param  array{qty: int}  $data
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $lineId, array $data): array
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

        $qty = (int) $data['qty'];
        if ($qty < 1) {
            return (new RemoveRetailerCartLine($this->carts, $this->shopping))($user, $lineId);
        }

        $line->qty = $qty;
        $line->save();
        $removed = $this->carts->reprice($cart, $ctx['zone_id'], $ctx['retailer_id']);
        $payload = $this->carts->presentRetailer($cart->fresh(['sections.lines']));
        $payload['removed_offers'] = $removed;

        return $payload;
    }
}
