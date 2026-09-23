<?php

declare(strict_types=1);

namespace Modules\Tenancy\Infrastructure;

use Modules\Core\Contracts\FeatureFlags;
use Modules\Tenancy\Domain\Models\FeatureFlag;
use Modules\Tenancy\Domain\Models\FeatureFlagOverride;

final class EloquentFeatureFlags implements FeatureFlags
{
    public function forChannel(int $channelId): array
    {
        $out = [];
        foreach (FeatureFlag::query()->get() as $flag) {
            $override = FeatureFlagOverride::query()
                ->where('feature_key', $flag->key)
                ->where('channel_id', $channelId)
                ->first();

            if ($override !== null) {
                $out[$flag->key] = (bool) $override->enabled;

                continue;
            }

            if (! $flag->enabled_globally) {
                $out[$flag->key] = false;

                continue;
            }

            $bucket = crc32($flag->key.'.'.$channelId) % 100;
            $out[$flag->key] = $bucket < (int) $flag->rollout_percent;
        }

        return $out;
    }

    public function forApp(string $scope): array
    {
        $out = [];
        foreach (FeatureFlag::query()->get() as $flag) {
            $scopes = $flag->scopes ?? [];
            if ($scopes !== [] && ! in_array($scope, $scopes, true)) {
                continue;
            }
            $out[$flag->key] = (bool) $flag->enabled_globally && (int) $flag->rollout_percent >= 100;
        }

        return $out;
    }
}
