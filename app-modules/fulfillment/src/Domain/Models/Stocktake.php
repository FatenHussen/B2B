<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stocktake extends Model
{
    protected $fillable = [
        'warehouse_id',
        'scope',
        'status',
        'counted_by',
        'approved_by',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(StocktakeLine::class);
    }
}
