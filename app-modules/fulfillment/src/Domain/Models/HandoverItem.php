<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['handover_id', 'sub_order_id', 'package_ids'];

    protected function casts(): array
    {
        return ['package_ids' => 'array'];
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(Handover::class);
    }
}
