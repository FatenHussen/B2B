<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * A back-office note on a channel (BE-T04, EP-AD-052 `internal_notes`). Append-only:
 * no `updated_at`.
 *
 * Channel-owned (rule 10): strict scope on `channel_id`. Written by the create action
 * with the channel id set explicitly, which needs no tenant; read from the back office
 * under `Tenant::as($channelId)`.
 */
class ChannelInternalNote extends Model
{
    use BelongsToChannel;

    protected $table = 'channel_internal_notes';

    protected string $channelColumn = 'channel_id';

    protected $fillable = ['channel_id', 'body', 'actor_type', 'actor_id', 'created_at'];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
