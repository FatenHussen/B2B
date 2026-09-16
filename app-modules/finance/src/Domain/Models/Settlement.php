<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $rep_id
 * @property int $amount
 * @property string $operation_no
 * @property string $receipt_pdf_url
 */
class Settlement extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'rep_id',
        'amount',
        'operation_no',
        'receipt_pdf_url',
    ];
}
