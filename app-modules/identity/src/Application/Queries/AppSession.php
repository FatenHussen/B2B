<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Core\Contracts\AccessCatalog;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RepCommercialLimits;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Models\AppUser;

final class AppSession
{
    public function __construct(
        private readonly AccessCatalog $access,
        private readonly RepDirectory $reps,
        private readonly RepCommercialLimits $limits,
        private readonly RepDutyLookup $duty,
        private readonly ChannelDirectory $channels,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(AppUser $user): array
    {
        $user->load(['retailerProfile', 'repProfile']);

        $payload = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'user_type' => $user->kind?->value,
                'profile_completed' => $user->profileCompleted(),
                'avatar' => null,
            ],
            'permissions' => $this->access->permissionsFor($user),
            'feature_flags' => config('app.feature_flags', [
                'offline_orders' => true,
                'loyalty' => true,
            ]),
            'sync_cursor' => '',
            'server_time' => now()->timezone('Asia/Damascus')->toIso8601String(),
            'requires_legal_accept' => false,
            'legal' => [
                'privacy_version' => '2026-03',
                'terms_version' => '2026-01',
            ],
        ];

        if ($user->kind === AppUserKind::Rep) {
            $channelId = $this->reps->channelIdForUser((int) $user->id);
            $payload['commercial_limits'] = [
                'max_discount_percent' => $channelId === null ? 0 : $this->limits->maxDiscountPercent($channelId, (int) $user->id),
                'max_cash_hold' => $channelId === null ? 0 : $this->limits->maxCashHold($channelId, (int) $user->id),
            ];
            $payload['duty'] = [
                'on_duty' => $this->duty->isOnDuty((int) $user->id),
                'tracking_enabled' => $this->duty->trackingEnabled((int) $user->id),
            ];
            // BF-06 — pending/disabled reps can still read session; surface why
            // operational routes return 403 insufficient_permission.
            $payload['status'] = $user->repProfile?->status?->value;
            $payload['channel'] = $channelId === null
                ? null
                : [
                    'id' => $channelId,
                    'name' => $this->channels->name($channelId),
                ];
        }

        return $payload;
    }
}
