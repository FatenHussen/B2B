<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Inventory\Domain\Enums\MovementType;

class StockMovement extends Model
{
    use BelongsToChannel;

    public $timestamps = false;

    protected $fillable = [
        'supply_channel_id',
        'warehouse_id',
        'product_id',
        'variant_id',
        'type',
        'qty_delta',
        'qty_before',
        'qty_after',
        'reason',
        'actor_type',
        'actor_id',
        'ref_type',
        'ref_id',
        'at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'qty_delta' => 'integer',
            'qty_before' => 'integer',
            'qty_after' => 'integer',
            'at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new DomainException(__('inventory.movement_immutable'), 'conflict', 409);
        });
        static::deleting(function (): never {
            throw new DomainException(__('inventory.movement_immutable'), 'conflict', 409);
        });
    }
}
