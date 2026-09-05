<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $device_token_hash
 * @property string $pin_hash
 * @property int $warehouse_id
 * @property int $channel_id
 * @property int|null $warehouse_user_id
 * @property string|null $label
 * @property Carbon|null $revoked_at
 */
class WarehouseDevice extends Model
{
    protected $fillable = [
        'device_token_hash',
        'pin_hash',
        'warehouse_id',
        'channel_id',
        'warehouse_user_id',
        'label',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(WarehouseUser::class, 'warehouse_user_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
