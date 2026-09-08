<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Support\Concerns\BelongsToChannel;

class CartSection extends Model
{
    use BelongsToChannel;

    /**
     * This table names its channel `channel_id`, not `supply_channel_id`.
     * See CLAUDE.md rule 10 — both names count, and the trait reads this one.
     */
    protected string $channelColumn = 'channel_id';

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
