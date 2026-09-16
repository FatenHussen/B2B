<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $retailer_id
 * @property int|null $invoice_id
 * @property int $amount
 * @property string $method
 * @property string $receipt_no
 */
class Payment extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'retailer_id',
        'rep_id',
        'invoice_id',
        'amount',
        'method',
        'source',
        'receipt_no',
        'paid_at',
        'client_op_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }
}
