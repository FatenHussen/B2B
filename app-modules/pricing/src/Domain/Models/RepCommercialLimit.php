<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RepCommercialLimit extends Model
{
    protected $fillable = [
        'channel_id',
        'rep_id',
        'max_discount_percent',
        'max_cash_hold',
    ];

    protected function casts(): array
    {
        return [
            'max_discount_percent' => 'integer',
            'max_cash_hold' => 'integer',
        ];
    }
}
