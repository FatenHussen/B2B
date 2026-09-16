<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $retailer_id
 * @property int $credit_limit
 * @property int $grace_days
 * @property string $on_exceed
 */
class RetailerCreditLimit extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'retailer_id',
        'credit_limit',
        'grace_days',
        'on_exceed',
    ];
}
