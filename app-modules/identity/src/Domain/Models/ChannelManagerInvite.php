<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelManagerInvite extends Model
{
    protected $fillable = [
        'channel_id',
        'token_hash',
        'invite_via',
        'expires_at',
        'consumed_at',
        'revoked_at',
        'created_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
