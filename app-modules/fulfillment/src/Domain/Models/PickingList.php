<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fulfillment\Domain\Enums\PickingStatus;

class PickingList extends Model
{
    protected $fillable = [
        'sub_order_id',
        'warehouse_id',
        'channel_id',
        'status',
        'due_at',
        'pick_path_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => PickingStatus::class,
            'due_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PickingLine::class);
    }
}
