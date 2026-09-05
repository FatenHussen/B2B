<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Inventory\Domain\Enums\TransferStatus;

class StockTransfer extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => TransferStatus::class];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }
}
