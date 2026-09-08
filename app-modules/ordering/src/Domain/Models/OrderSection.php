<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Support\Concerns\BelongsToChannel;

class OrderSection extends Model
{
    use BelongsToChannel;

    /**
     * This table names its channel `channel_id`, not `supply_channel_id`.
     * See CLAUDE.md rule 10 — both names count, and the trait reads this one.
     */
    protected string $channelColumn = 'channel_id';

    protected $fillable = [
        'order_id',
        'channel_id',
        'opaque_ref',
        'note',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
