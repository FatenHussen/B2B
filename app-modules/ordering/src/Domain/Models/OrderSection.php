<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderSection extends Model
{
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
