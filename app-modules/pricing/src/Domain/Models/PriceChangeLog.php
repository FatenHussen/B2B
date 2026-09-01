<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

class PriceChangeLog extends Model
{
    use BelongsToChannel;

    public $timestamps = false;

    protected $fillable = [
        'supply_channel_id',
        'product_id',
        'actor_user_id',
        'before',
        'after',
        'reason',
        'at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'integer',
            'after' => 'integer',
            'at' => 'datetime',
        ];
    }
}
