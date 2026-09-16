<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $rep_id
 * @property string $type
 * @property int $amount
 * @property int|null $settlement_id
 * @property string|null $operation_no
 */
class WalletTransaction extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'rep_id',
        'type',
        'amount',
        'settlement_id',
        'operation_no',
    ];
}
