<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListSchedule extends Model
{
    protected $fillable = [
        'price_list_id',
        'effective_from',
        'payload',
        'job_id',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'effective_from' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
