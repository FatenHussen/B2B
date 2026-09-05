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

    public function sections(): HasMany
    {
        return $this->hasMany(OrderSection::class);
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }
}
