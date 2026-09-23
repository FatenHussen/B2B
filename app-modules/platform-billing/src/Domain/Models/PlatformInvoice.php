<?php

declare(strict_types=1);

namespace Modules\PlatformBilling\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformInvoice extends Model
{
    protected $fillable = [
        'no', 'channel_id', 'subscription_id', 'amount', 'issued_at', 'due_at', 'pdf_path',
    ];

    /** @var list<string> */
    protected $guarded = ['status'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }
}
