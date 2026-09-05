<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fulfillment\Domain\Enums\HandoverStatus;

class Handover extends Model
{
    protected $fillable = [
        'warehouse_id',
        'rep_id',
        'status',
        'temp_code',
        'opened_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => HandoverStatus::class,
            'opened_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(HandoverItem::class);
    }
}
