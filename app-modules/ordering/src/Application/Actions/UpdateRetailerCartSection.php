<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Application\Support\CartAssembler;
use Modules\Ordering\Domain\Models\CartSection;

final class UpdateRetailerCartSection
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly RetailerShoppingContext $shopping,
    ) {}

    /**
     * @param  array{note?: string|null, scheduled_at?: string|null}  $data
     * @return array<string, mixed>
     */
    public function __invoke(object $user, string $ref, array $data): array
    {
        $ctx = $this->shopping->for($user);
        $cart = $this->carts->activeFor($user);
        $section = CartSection::query()->where('cart_id', $cart->id)->where('opaque_ref', $ref)->first();
        if ($section === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $section->forceFill([
            'note' => $data['note'] ?? $section->note,
            'scheduled_at' => $data['scheduled_at'] ?? $section->scheduled_at,
        ])->save();

        $this->carts->reprice($cart, $ctx['zone_id'], $ctx['retailer_id']);

        return $this->carts->presentRetailer($cart->fresh(['sections.lines']));
    }
}
