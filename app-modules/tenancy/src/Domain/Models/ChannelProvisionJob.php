<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * The provisioning job EP-AD-051 returns as `provisioning_job_id` and EP-AD-053
 * retries (BE-T05). `payload` holds everything the job materialises, so a retry needs
 * nothing from the request that started it.
 *
 * Channel-owned (rule 10): strict scope on `channel_id`. Written by the create action
 * with the channel id set explicitly, which needs no tenant; read from the back office
 * under `Tenant::as($channelId)`.
 */
class ChannelProvisionJob extends Model
{
    use BelongsToChannel;

    protected $table = 'channel_provision_jobs';

    protected string $channelColumn = 'channel_id';

    protected $fillable = ['public_id', 'channel_id', 'status', 'payload', 'error', 'attempts', 'started_at', 'finished_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
