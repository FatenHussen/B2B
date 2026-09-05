<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Database\Factories\PlatformUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Domain\Enums\UserStatus;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property UserStatus $status
 * @property string|null $two_factor_secret
 * @property array<int, string>|null $two_factor_recovery_codes
 * @property Carbon|null $last_login_at
 */
class PlatformUser extends Authenticatable
{
    /** @use HasFactory<PlatformUserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $table = 'platform_users';

    protected string $guard_name = 'platform';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'two_factor_secret',
        'pending_two_factor_secret',
        'two_factor_recovery_codes',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'pending_two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'two_factor_secret' => 'encrypted',
            'pending_two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'last_login_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return is_string($this->two_factor_secret) && $this->two_factor_secret !== '';
    }

    protected static function newFactory(): PlatformUserFactory
    {
        return PlatformUserFactory::new();
    }
}
