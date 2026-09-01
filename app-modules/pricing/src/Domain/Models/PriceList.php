<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Pricing\Domain\Enums\AdjustmentMode;
use Modules\Pricing\Domain\Enums\PriceListStatus;
use Modules\Pricing\Domain\Enums\PriceListType;

class PriceList extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'name',
        'type',
        'status',
        'group_id',
        'retailer_id',
        'adjustment_mode',
        'adjustment_value',
        'effective_from',
        'effective_to',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => PriceListType::class,
            'status' => PriceListStatus::class,
            'adjustment_mode' => AdjustmentMode::class,
            'adjustment_value' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function zones(): HasMany
    {
        return $this->hasMany(PriceListZone::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PriceListSchedule::class);
    }
}
