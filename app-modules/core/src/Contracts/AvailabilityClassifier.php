<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface AvailabilityClassifier
{
    /**
     * L2: active → in_stock, otherwise out_of_stock. Inventory replaces this in SP-09.
     */
    public function classify(int $productId): string;
}
