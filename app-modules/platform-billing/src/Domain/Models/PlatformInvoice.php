<?php

declare(strict_types=1);

namespace Modules\PlatformBilling\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * An invoice the platform issues to a channel (PA-07 / EP-AD-103 … 107).
 *
 * Written by the platform back office about a channel, on routes that set no
 * tenant. Relaxed channel scope so that the first `/channel/*` route to read it is
 * isolated the day it lands (see `BelongsToChannel::channelScopeOptional()`).
 * `amount` is minor units (rule 7). `status` is guarded (rule 8).
 *
 * @property int $id
 * @property string $no
 * @property int $channel_id
 * @property int|null $subscription_id
 * @property int $amount
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property string $status
 * @property string|null $pdf_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PlatformInvoice extends Model
{
    use BelongsToChannel;

    protected string $channelColumn = 'channel_id';

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'no', 'channel_id', 'subscription_id', 'amount', 'issued_at', 'due_at', 'pdf_path',
    ];

    /** @var list<string> */
    protected $guarded = ['status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel_id' => 'integer',
            'subscription_id' => 'integer',
            'amount' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }
}
