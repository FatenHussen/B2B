<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * An activity type a channel serves (BE-T04).
 *
 * Channel-owned (rule 10): strict scope on `channel_id`. Written by the create action
 * with the channel id set explicitly, which needs no tenant; read from the back office
 * under `Tenant::as($channelId)`.
 */
class ChannelActivityType extends Model
{
    use BelongsToChannel;

    protected $table = 'channel_activity_types';

    protected string $channelColumn = 'channel_id';

    protected $fillable = ['channel_id', 'activity_type_id'];
}
