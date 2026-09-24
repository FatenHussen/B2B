<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $retailer_id
 * @property Carbon $viewed_at
 */
class OfferView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'offer_id',
        'retailer_id',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }
}
