<?php

declare(strict_types=1);

namespace Modules\Delivery\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RepRating extends Model
{
    protected $fillable = [
        'retailer_id',
        'rep_id',
        'sub_order_id',
        'stars',
        'note',
        'tags',
    ];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }
}
