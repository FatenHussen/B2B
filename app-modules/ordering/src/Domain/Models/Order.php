<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Ordering\Domain\Enums\OrderSource;

class Order extends Model
{
    protected $fillable = [
        'retailer_id',
        'source',
        'order_no',
        'status',
        'currency',
        'client_created_at',
        'offline_created',
        'repricing_diff',
    ];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'offline_created' => 'boolean',
            'client_created_at' => 'datetime',
        ];
    }

    /**
     * The channel scope is lifted on both relations, with the reason here (rule 10,
     * BE-C12). An order belongs to one retailer and is split into one section and one
     * sub-order per channel; a section or sub-order cannot belong to another retailer
     * than its order. On `/app/retailer/*` there is no tenant and the order is reached
     * through its owner. The one channel-side reader, `ShowChannelSubOrder`, filters the
     * sections it loads by `channel_id` explicitly, so lifting the scope here widens
     * nothing there.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(OrderSection::class)->withoutGlobalScope('channel');
    }

    /** See `sections()`. */
    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class)->withoutGlobalScope('channel');
    }
}
