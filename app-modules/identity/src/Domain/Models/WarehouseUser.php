<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Database\Factories\WarehouseUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Domain\Enums\UserStatus;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property UserStatus $status
 * @property Carbon|null $last_login_at
 */
class WarehouseUser extends Authenticatable
{
    /** @use HasFactory<WarehouseUserFactory> */
    use HasApiTokens, HasFactory, HasRoles;

    protected $table = 'warehouse_users';

    protected string $guard_name = 'warehouse';

    protected $fillable = [
        'name',
        'status',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    protected static function newFactory(): WarehouseUserFactory
    {
        return WarehouseUserFactory::new();
    }

    public function defaultChannelId(): ?int
    {
        $id = WarehouseDevice::query()
            ->where('warehouse_user_id', $this->id)
            ->whereNull('revoked_at')
            ->value('channel_id');

        return $id !== null ? (int) $id : null;
    }

    public function warehouseId(): ?int
    {
        $id = WarehouseDevice::query()
            ->where('warehouse_user_id', $this->id)
            ->whereNull('revoked_at')
            ->value('warehouse_id');

        return $id !== null ? (int) $id : null;
    }
}
