<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $offer_id
 * @property int|null $product_id
 * @property int $qty
 * @property int|null $discount_percent
 * @property int|null $discount_amount
 */
class OfferReward extends Model
{
    public $timestamps = false;

    protected $fillable = ['offer_id', 'product_id', 'qty', 'discount_percent', 'discount_amount'];
}
