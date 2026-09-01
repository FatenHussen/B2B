<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Access\Domain\Enums\AccessChangeStatus;
use Modules\Access\Domain\Enums\AccessChangeType;

/**
 * @property int $id
 * @property AccessChangeType $type
 * @property AccessChangeStatus $status
 * @property string|null $permission
 * @property string|null $action
 * @property array<string, mixed> $payload
 * @property int $requester_id
 * @property int|null $approver_id
 * @property string|null $reason
 * @property int|null $subject_id
 */
class AccessChangeRequest extends Model
{
    protected $fillable = [
        'type',
        'status',
        'permission',
        'action',
        'payload',
        'requester_id',
        'approver_id',
        'reason',
        'subject_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccessChangeType::class,
            'status' => AccessChangeStatus::class,
            'payload' => 'array',
        ];
    }
}
