<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickingLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'picking_list_id',
        'product_id',
        'variant_id',
        'qty_required',
        'qty_picked',
        'location_id',
        'barcode',
        'manual',
        'shortage_reason',
    ];

    protected function casts(): array
    {
        return ['manual' => 'boolean'];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(PickingList::class, 'picking_list_id');
    }
}
