<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * Per-channel feature override (PA-08).
 *
 * Platform-written *about* a channel: listed across channels on `GET /platform/features`,
 * written/deleted from the back office, resolved by explicit `channel_id` in
 * `FeatureFlags::forChannel()`. Relaxed — filters when a tenant is set, tolerates its
 * absence on `/platform/*` (no tenant after BF-07 closed the `X-Channel-Id` switch).
 * Was exempt while that switch existed; re-evaluated to relaxed in the same commit.
 */
class FeatureFlagOverride extends Model
{
    use BelongsToChannel;

    protected string $channelColumn = 'channel_id';

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'feature_key',
        'channel_id',
        'enabled',
        'reason',
        'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'channel_id' => 'integer',
            'actor_id' => 'integer',
        ];
    }
}
