<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $receipt_no
 * @property int $rep_id
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property int|null $payment_id
 */
class ReceiptReservation extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'receipt_no',
        'rep_id',
        'expires_at',
        'consumed_at',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
