<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Ordering\Domain\Enums\OrderSource;
use Modules\Ordering\Domain\Enums\SubOrderStatus;

class SubOrder extends Model
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
        'retailer_id',
        'zone_id',
        'source',
        'sub_order_no',
        'status',
        'subtotal',
        'discount',
        'total',
        'rep_id',
        'scheduled_at',
        'credit_check',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubOrderStatus::class,
            'source' => OrderSource::class,
            'credit_check' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SubOrderLine::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubOrderEvent::class)->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SubOrderAssignment::class);
    }
}
