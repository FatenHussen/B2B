<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class OfferMedia extends Model
{
    public $timestamps = false;

    protected $fillable = ['offer_id', 'media_id', 'order'];
}
