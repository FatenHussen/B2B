<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Application\Support\CartAssembler;
use Modules\Ordering\Domain\Models\CartLine;

final class UpdateRepCartLine
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly RetailerDirectory $retailers,
    ) {}

    /**
     * @param  array{qty: int}  $data
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $lineId, array $data): array
    {
        $qty = (int) $data['qty'];
        if ($qty < 1) {
            return (new RemoveRepCartLine($this->carts, $this->retailers))($user, $lineId);
        }

        $cart = $this->carts->activeFor($user);
        $line = CartLine::query()
            ->whereKey($lineId)
            ->whereHas('section', fn ($q) => $q->where('cart_id', $cart->id))
            ->first();
        if ($line === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $line->qty = $qty;
        $line->save();

        $section = $line->section;
        $retailerId = (int) $section->retailer_id;
        $this->carts->reprice(
            $cart,
            (int) $this->retailers->zoneId($retailerId),
            $retailerId,
            (int) $section->channel_id,
        );

        return $this->carts->presentRep($cart->fresh(['sections.lines']));
    }
}
