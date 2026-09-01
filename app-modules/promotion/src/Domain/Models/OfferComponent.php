<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class OfferComponent extends Model
{
    public $timestamps = false;

    protected $fillable = ['offer_id', 'product_id', 'qty'];
}
