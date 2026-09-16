<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Enums\DualApprovalStatus;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property DualApprovalStatus $status
 * @property string $permission
 * @property string $action
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property int $requester_id
 * @property int|null $approver_id
 * @property string|null $reason
 */
class DualApprovalRequest extends Model
{
    use BelongsToChannel;

    protected $guarded = ['status'];

    protected $fillable = [
        'supply_channel_id',
        'permission',
        'action',
        'payload',
        'payload_hash',
        'requester_id',
        'approver_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => DualApprovalStatus::class,
            'payload' => 'array',
        ];
    }
}
