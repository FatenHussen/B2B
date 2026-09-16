<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $invoice_id
 * @property string $no
 * @property int $total
 * @property string $reason
 */
class CreditNote extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'invoice_id',
        'no',
        'total',
        'reason',
    ];
}
