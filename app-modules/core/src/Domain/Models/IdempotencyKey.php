<?php

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $status
 * @property string|null $request_hash
 * @property string|null $response_body
 * @property int|null $status_code
 */
class IdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'user_id',
        'endpoint',
        'request_hash',
        'response_body',
        'status_code',
        'status',
        'created_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
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
}
