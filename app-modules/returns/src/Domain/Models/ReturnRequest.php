<?php

declare(strict_types=1);

namespace Modules\Returns\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Support\Concerns\BelongsToChannel;

class ReturnRequest extends Model
{
    use BelongsToChannel;

    /**
     * This table names its channel `channel_id`, not `supply_channel_id`.
     * See CLAUDE.md rule 10 — both names count, and the trait reads this one.
     */
    protected string $channelColumn = 'channel_id';

    protected $fillable = [
        'channel_id',
        'sub_order_id',
        'zone_id',
        'rep_id',
        'requester_type',
        'requester_id',
        'type',
        'status',
        'request_no',
        'sla_due_at',
        'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReturnLine::class);
    }
}
