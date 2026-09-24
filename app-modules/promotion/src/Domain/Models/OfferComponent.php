<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $offer_id
 * @property int $product_id
 * @property int $qty
 */
class OfferComponent extends Model
{
    public $timestamps = false;

    protected $fillable = ['offer_id', 'product_id', 'qty'];
}
