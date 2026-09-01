<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $platform_user_id
 * @property \Illuminate\Support\Carbon $expires_at
 * @property int $attempts
 */
class OtpChallenge extends Model
{
    protected $fillable = [
        'public_id',
        'platform_user_id',
        'expires_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'platform_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
