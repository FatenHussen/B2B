<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain;

use Modules\Core\Contracts\ChannelLimits;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Tenancy\Domain\Models\ChannelLimit;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * The effective plan limits of a channel (BE-T12).
 *
 * Resolution, per key: an override that has not expired → the channel's own limits row
 * (written from its plan at provisioning) → the plan's limits → the platform defaults.
 *
 * An override expires by comparison at read time: `temporary_until` is checked against
 * the clock on every call, so the moment it passes the base value is what every caller
 * sees. No scheduled job has to run, on time or at all, for the revert to happen — BE-T12
 * acceptance criterion one.
 */
final class ChannelLimitResolver implements ChannelLimits
{
    /**
     * What a channel gets when nothing was ever configured for it. These are the
     * `growth` figures the catalog shows on EP-AD-050/051; `skus` keeps the 5000 the
     * previous stub answered so no existing caller sees a tighter cap.
     */
    public const DEFAULTS = [
        'users' => 25,
        'warehouses' => 2,
        'reps' => 20,
        'skus' => 5000,
        'storage_mb' => 2048,
    ];

    public function skuCap(int $channelId): int
    {
        return $this->cap($channelId, 'skus');
    }

    public function cap(int $channelId, string $key): int
    {
        return $this->caps($channelId)[$key] ?? 0;
    }

    /**
     * @return array<string, int>
     */
    public function caps(int $channelId): array
    {
        $row = Tenant::as($channelId, fn (): ?ChannelLimit => ChannelLimit::query()->first());
        $plan = $this->planLimits($channelId);
        $overrideLive = $row !== null && ($row->temporary_until === null || $row->temporary_until->isFuture());

        $caps = [];
        foreach (self::KEYS as $key) {
            $override = $overrideLive ? $row->getAttribute('override_'.$key) : null;
            $base = $row?->getAttribute($key);

            $caps[$key] = match (true) {
                $override !== null => (int) $override,
                $base !== null => (int) $base,
                isset($plan[$key]) => (int) $plan[$key],
                default => self::DEFAULTS[$key],
            };
        }

        return $caps;
    }

    public function assertCanAdd(int $channelId, string $key, int $currentUsage): void
    {
        $max = $this->cap($channelId, $key);

        if ($currentUsage >= $max) {
            throw DomainException::of(
                ErrorCode::PlanLimitExceeded,
                __('tenancy.plan_limit_exceeded', ['limit' => $key, 'max' => $max]),
                ['limit' => $key, 'max' => $max, 'used' => $currentUsage],
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function planLimits(int $channelId): array
    {
        $planId = SupplyChannel::query()->whereKey($channelId)->value('plan_id');
        if ($planId === null) {
            return [];
        }

        $limits = ChannelPlan::query()->whereKey($planId)->value('limits');
        if (is_string($limits)) {
            $limits = json_decode($limits, true);
        }

        return is_array($limits) ? array_map('intval', array_intersect_key($limits, array_flip(self::KEYS))) : [];
    }
}
