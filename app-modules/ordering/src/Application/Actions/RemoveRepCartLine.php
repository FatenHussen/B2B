<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Application\Support\CartAssembler;
use Modules\Ordering\Domain\Models\CartLine;

final class RemoveRepCartLine
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly RetailerDirectory $retailers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $lineId): array
    {
        $cart = $this->carts->activeFor($user);
        $line = CartLine::query()
            ->whereKey($lineId)
            ->whereHas('section', fn ($q) => $q->where('cart_id', $cart->id))
            ->first();
        if ($line === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $section = $line->section;
        $retailerId = (int) $section->retailer_id;
        $channelId = (int) $section->channel_id;

        if ($line->offer_id) {
            CartLine::query()
                ->whereHas('section', fn ($q) => $q->where('cart_id', $cart->id))
                ->where('offer_id', $line->offer_id)
                ->delete();
        } else {
            $line->delete();
        }

        foreach ($cart->sections as $open) {
            if ($open->lines()->count() === 0) {
                $open->delete();
            }
        }

        $this->carts->reprice($cart, (int) $this->retailers->zoneId($retailerId), $retailerId, $channelId);

        return $this->carts->presentRep($cart->fresh(['sections.lines']));
    }
}
