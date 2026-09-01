<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Database\Factories\ChannelUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Domain\Enums\UserStatus;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property UserStatus $status
 * @property \Illuminate\Support\Carbon|null $last_login_at
 */
class ChannelUser extends Authenticatable
{
    /** @use HasFactory<ChannelUserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $table = 'channel_users';

    protected string $guard_name = 'channel';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ChannelUserChannel::class, 'channel_user_id');
    }

    /**
     * @return list<array{id: int, is_default: bool}>
     */
    public function channelMemberships(): array
    {
        return $this->memberships()
            ->orderByDesc('is_default')
            ->get(['channel_id', 'is_default'])
            ->map(fn (ChannelUserChannel $row) => [
                'id' => (int) $row->channel_id,
                'is_default' => (bool) $row->is_default,
            ])
            ->all();
    }

    public function defaultChannelId(): ?int
    {
        $row = $this->memberships()->orderByDesc('is_default')->first();

        return $row !== null ? (int) $row->channel_id : null;
    }

    protected static function newFactory(): ChannelUserFactory
    {
        return ChannelUserFactory::new();
    }
}
