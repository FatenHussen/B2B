<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * Manual-approval queue row when retailer credit on_exceed is manual_approval.
 *
 * @property int $id
 * @property int $supply_channel_id
 * @property int $retailer_id
 * @property int $amount_minor
 * @property int $outstanding_at_request
 * @property int $credit_limit_at_request
 * @property string $status
 * @property string|null $reason
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $consumed_at
 */
class CreditApprovalRequest extends Model
{
    use BelongsToChannel;

    protected $guarded = ['status'];

    protected $fillable = [
        'supply_channel_id',
        'retailer_id',
        'amount_minor',
        'outstanding_at_request',
        'credit_limit_at_request',
        'reason',
        'decided_by',
        'decided_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'outstanding_at_request' => 'integer',
            'credit_limit_at_request' => 'integer',
            'decided_by' => 'integer',
            'decided_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
