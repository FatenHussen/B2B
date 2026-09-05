<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubOrderEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sub_order_id',
        'stage',
        'at',
        'actor_type',
        'actor_id',
    ];

    protected function casts(): array
    {
        return ['at' => 'datetime'];
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}
