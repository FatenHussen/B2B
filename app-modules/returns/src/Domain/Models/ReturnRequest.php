<?php

declare(strict_types=1);

namespace Modules\Returns\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    protected $fillable = [
        'channel_id',
        'sub_order_id',
        'requester_type',
        'requester_id',
        'type',
        'status',
        'request_no',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(ReturnLine::class);
    }
}
