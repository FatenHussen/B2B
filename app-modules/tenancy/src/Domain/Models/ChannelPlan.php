<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A subscription plan, as light as it can be (BE-T04): a key, a name and the default
 * limits a channel on it starts with. Pricing and billing are BE4-BIL01.
 *
 * Platform reference data — no channel scope.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property array{users: int, warehouses: int, reps: int, skus: int, storage_mb: int} $limits
 * @property bool $is_active
 */
class ChannelPlan extends Model
{
    protected $table = 'channel_plans';

    protected $fillable = ['key', 'name', 'limits', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
