<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Access\Domain\Enums\TempGrantStatus;

/**
 * @property int $id
 * @property string $grantee_type
 * @property int $grantee_id
 * @property string $permission
 * @property TempGrantStatus $status
 * @property int $duration_minutes
 * @property \Illuminate\Support\Carbon|null $granted_until
 * @property \Illuminate\Support\Carbon|null $expires_request_at
 * @property int|null $request_id
 * @property int $requester_id
 * @property int|null $approver_id
 * @property string $reason
 * @property \Illuminate\Support\Carbon|null $revoked_at
 */
class TempGrant extends Model
{
    protected $fillable = [
        'grantee_type',
        'grantee_id',
        'permission',
        'status',
        'duration_minutes',
        'granted_until',
        'expires_request_at',
        'request_id',
        'requester_id',
        'approver_id',
        'reason',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TempGrantStatus::class,
            'granted_until' => 'datetime',
            'expires_request_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isLive(): bool
    {
        return $this->status === TempGrantStatus::Active
            && $this->revoked_at === null
            && $this->granted_until !== null
            && $this->granted_until->isFuture();
    }
}
