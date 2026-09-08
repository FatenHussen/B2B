<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

class RepCommercialLimit extends Model
{
    use BelongsToChannel;

    /**
     * This table names its channel `channel_id`, not `supply_channel_id`.
     * See CLAUDE.md rule 10 — both names count, and the trait reads this one.
     */
    protected string $channelColumn = 'channel_id';

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
