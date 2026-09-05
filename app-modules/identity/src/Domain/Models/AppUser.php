<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Database\Factories\AppUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Enums\UserStatus;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property AppUserKind|null $kind
 * @property UserStatus $status
 * @property Carbon|null $last_login_at
 * @property RetailerProfile|null $retailerProfile
 * @property RepProfile|null $repProfile
 */
class AppUser extends Authenticatable
{
    /** @use HasFactory<AppUserFactory> */
    use HasApiTokens, HasFactory;

    protected $table = 'app_users';

    protected $fillable = [
        'name',
        'phone',
        'kind',
        'status',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AppUserKind::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function retailerProfile(): HasOne
    {
        return $this->hasOne(RetailerProfile::class);
    }

    public function repProfile(): HasOne
    {
        return $this->hasOne(RepProfile::class);
    }

    public function defaultChannelId(): ?int
    {
        if ($this->kind !== AppUserKind::Rep) {
            return null;
        }

        $id = RepProfile::query()->where('app_user_id', $this->id)->value('channel_id');

        return $id !== null ? (int) $id : null;
    }

    public function profileCompleted(): bool
    {
        if ($this->kind === AppUserKind::Retailer) {
            return $this->retailerProfile !== null;
        }

        if ($this->kind === AppUserKind::Rep) {
            return $this->repProfile !== null;
        }

        return false;
    }

    protected static function newFactory(): AppUserFactory
    {
        return AppUserFactory::new();
    }
}
