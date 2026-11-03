<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shortage_allocation_id
 * @property int $sub_order_id
 * @property int $sub_order_line_id
 * @property int $qty_requested
 * @property int $qty_allocated
 * @property string $status
 */
class ShortageAllocationLine extends Model
{
    protected $fillable = [
        'shortage_allocation_id',
        'sub_order_id',
        'sub_order_line_id',
        'qty_requested',
        'qty_allocated',
        'status',
    ];

    /** @return BelongsTo<ShortageAllocation, $this> */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(ShortageAllocation::class, 'shortage_allocation_id');
    }
}
