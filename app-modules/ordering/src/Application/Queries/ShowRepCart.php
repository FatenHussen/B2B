<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Ordering\Application\Support\CartAssembler;

final class ShowRepCart
{
    public function __construct(private readonly CartAssembler $carts) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user): array
    {
        return $this->carts->presentRep($this->carts->activeFor($user));
    }
}
