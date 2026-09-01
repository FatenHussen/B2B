<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PriceListZone extends Model
{
    public $timestamps = false;

    protected $fillable = ['price_list_id', 'zone_id'];
}
