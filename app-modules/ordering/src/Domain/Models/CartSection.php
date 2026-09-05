<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartSection extends Model
{
    protected $fillable = [
        'cart_id',
        'channel_id',
        'opaque_ref',
        'retailer_id',
        'note',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CartLine::class, 'section_id');
    }
}
