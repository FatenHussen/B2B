<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

class StockBalance extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'warehouse_id',
        'product_id',
        'variant_id',
        'on_hand',
        'reserved',
        'in_transit',
        'damaged',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'in_transit' => 'integer',
            'damaged' => 'integer',
        ];
    }

    public function available(): int
    {
        return max(0, (int) $this->on_hand - (int) $this->reserved);
    }
}
