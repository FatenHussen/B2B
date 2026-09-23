<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformInvite extends Model
{
    protected $fillable = [
        'email',
        'role_ids',
        'token_hash',
        'expires_at',
        'accepted_at',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'role_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
