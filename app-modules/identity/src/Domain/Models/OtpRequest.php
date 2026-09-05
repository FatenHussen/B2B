<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Identity\Domain\Enums\OtpChannelUsed;
use Modules\Identity\Domain\Enums\OtpPurpose;

/**
 * @property int $id
 * @property string $public_id
 * @property string $phone
 * @property string $code_hash
 * @property OtpPurpose $purpose
 * @property OtpChannelUsed $channel_used
 * @property string|null $client
 * @property string|null $ip
 * @property string|null $device_id
 * @property Carbon $expires_at
 * @property int $attempts
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 */
class OtpRequest extends Model
{
    protected $fillable = [
        'public_id',
        'phone',
        'code_hash',
        'purpose',
        'channel_used',
        'client',
        'ip',
        'device_id',
        'expires_at',
        'attempts',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'channel_used' => OtpChannelUsed::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
