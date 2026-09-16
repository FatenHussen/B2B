<?php

declare(strict_types=1);

namespace Modules\Delivery\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Delivery extends Model
{
    protected $fillable = ['sub_order_id', 'rep_id', 'status', 'reason', 'scheduled_at'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class);
    }

    public function completion(): HasOne
    {
        return $this->hasOne(DeliveryCompletion::class);
    }
}
