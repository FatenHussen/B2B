<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Ordering\Domain\Enums\AssignmentStatus;

class SubOrderAssignment extends Model
{
    protected $fillable = [
        'sub_order_id',
        'rep_id',
        'status',
        'reason',
    ];

    protected function casts(): array
    {
        return ['status' => AssignmentStatus::class];
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}
