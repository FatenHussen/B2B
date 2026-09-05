<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $platform_user_id
 * @property string|null $token_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $last_active_at
 */
class PlatformSession extends Model
{
    protected $fillable = [
        'platform_user_id',
        'token_id',
        'ip',
        'user_agent',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'platform_user_id');
    }
}
