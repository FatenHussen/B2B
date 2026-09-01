<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Enums;

enum OfferType: string
{
    case ProductDiscount = 'product_discount';
    case InvoiceDiscount = 'invoice_discount';
    case BuyXGetY = 'buy_x_get_y';
    case Bundle = 'bundle';
    case TieredDiscount = 'tiered_discount';
    case Gift = 'gift';
}
