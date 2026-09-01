<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Domain\Enums\RepSourcedShopStatus;

class RepSourcedShop extends Model
{
    protected $fillable = [
        'rep_id',
        'shop_name',
        'owner_name',
        'phone',
        'zone_id',
        'activity_type_id',
        'lat',
        'lng',
        'client_op_id',
        'status',
        'retailer_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => RepSourcedShopStatus::class,
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RepProfile::class, 'rep_id');
    }
}
