<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $platform_user_id
 * @property Carbon $confirmed_until
 */
class PasswordConfirmation extends Model
{
    protected $fillable = [
        'platform_user_id',
        'confirmed_until',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'platform_user_id');
    }

    public function isValid(): bool
    {
        return $this->confirmed_until->isFuture();
    }
}
