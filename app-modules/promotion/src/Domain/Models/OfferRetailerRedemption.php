<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $retailer_id
 * @property int $applied_count
 * @property int $qty_consumed
 */
class OfferRetailerRedemption extends Model
{
    protected $fillable = [
        'offer_id',
        'retailer_id',
        'applied_count',
        'qty_consumed',
    ];
}
