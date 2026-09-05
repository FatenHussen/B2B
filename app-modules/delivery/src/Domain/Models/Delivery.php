<?php

declare(strict_types=1);

namespace Modules\Delivery\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Delivery extends Model
{
    protected $fillable = ['sub_order_id', 'rep_id', 'status'];

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class);
    }

    public function completion(): HasOne
    {
        return $this->hasOne(DeliveryCompletion::class);
    }
}
