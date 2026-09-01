<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class OfferZone extends Model
{
    public $timestamps = false;

    protected $fillable = ['offer_id', 'zone_id'];
}
