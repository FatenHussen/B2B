<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

class Invoice extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'sub_order_id',
        'retailer_id',
        'rep_id',
        'no',
        'total',
        'status',
    ];
}
