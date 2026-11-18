<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetailerOfferFavorite extends Model
{
    protected $fillable = ['retailer_id', 'offer_id'];
}
