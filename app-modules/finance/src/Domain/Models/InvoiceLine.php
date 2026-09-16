<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $invoice_id
 * @property int|null $product_id
 * @property int $qty
 * @property int $amount
 */
class InvoiceLine extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'invoice_id',
        'product_id',
        'qty',
        'amount',
    ];
}
