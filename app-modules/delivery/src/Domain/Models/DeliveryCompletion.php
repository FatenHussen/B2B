<?php

declare(strict_types=1);

namespace Modules\Delivery\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryCompletion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'delivery_id',
        'invoice_id',
        'receipt_no',
        'delivered_at',
        'signature',
    ];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime'];
    }
}
