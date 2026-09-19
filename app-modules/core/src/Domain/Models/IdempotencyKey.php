<?php

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One caller's memory of one write, kept for 24 hours (ADR-05).
 *
 * A row is keyed by (guard, user_id, key), never by key alone: the same UUID presented
 * by two users, or by user 5 on two guards, is two rows, and a stored response is served
 * to nobody but the principal that created it (BE-C13).
 *
 * @property int $id
 * @property string $key
 * @property string $guard
 * @property int $user_id
 * @property string $endpoint
 * @property string|null $request_hash
 * @property string|null $response_body
 * @property int|null $status_code
 * @property string $status
 * @property Carbon|null $locked_until
 * @property Carbon|null $created_at
 * @property Carbon|null $completed_at
 */
class IdempotencyKey extends Model
{
    /**
     * The pair stored for a caller no guard authenticated. Both columns are NOT NULL
     * so the unique index holds for this pair too — MySQL treats NULL as distinct in a
     * unique index, and a nullable guard or user would let two anonymous requests with
     * one key both insert. Every anonymous caller shares this pair: on a route with no
     * guard there is nothing to tell them apart.
     */
    public const ANONYMOUS_GUARD = '';

    public const ANONYMOUS_USER_ID = 0;

    public $timestamps = false;

    protected $fillable = [
        'key',
        'guard',
        'user_id',
        'endpoint',
        'request_hash',
        'response_body',
        'status_code',
        'status',
        'locked_until',
        'created_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'locked_until' => 'datetime',
            'created_at' => 'datetime',
            'completed_at' => 'datetime',
            'status_code' => 'integer',
        ];
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Still held by the worker that is running the request. A `processing` row whose
     * lock has lapsed — or that carries none — belongs to nobody: the worker died before
     * it could finish, and the next retry may take the row over.
     */
    public function isLocked(): bool
    {
        return $this->isProcessing()
            && $this->locked_until !== null
            && $this->locked_until->isFuture();
    }
}
