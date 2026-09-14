<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * A governorate a channel covers (BE-T04). Zone coverage lives in Reference's
 * `channel_zone`, written by the provisioning job (BE-T05).
 *
 * Channel-owned (rule 10): strict scope on `channel_id`. Written by the create action
 * with the channel id set explicitly, which needs no tenant; read from the back office
 * under `Tenant::as($channelId)`.
 */
class ChannelGovernorate extends Model
{
    use BelongsToChannel;

    protected $table = 'channel_governorates';

    protected string $channelColumn = 'channel_id';

    protected $fillable = ['channel_id', 'governorate_id'];
}
