<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * A document attached at creation (BE-T04). `media_id` is stored as given; no media
 * module validates it yet (BE-X03).
 *
 * Channel-owned (rule 10): strict scope on `channel_id`. Written by the create action
 * with the channel id set explicitly, which needs no tenant; read from the back office
 * under `Tenant::as($channelId)`.
 */
class ChannelDocument extends Model
{
    use BelongsToChannel;

    protected $table = 'channel_documents';

    protected string $channelColumn = 'channel_id';

    protected $fillable = ['channel_id', 'media_id'];
}
