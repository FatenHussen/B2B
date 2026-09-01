<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ProductPricingWriter
{
    public function replace(int $channelId, int $productId, PricingDraft $draft): void;
}
