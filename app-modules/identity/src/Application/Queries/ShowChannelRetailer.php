<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RetailerCreditSnapshot;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RetailerGroup;
use Modules\Identity\Domain\Models\RetailerGroupMember;
use Modules\Identity\Domain\Models\RetailerProfile;

final class ShowChannelRetailer
{
    public function __construct(
        private readonly ChannelDirectory $channels,
        private readonly RetailerCreditSnapshot $credit,
        private readonly SubOrderLifecycle $orders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(int $retailerId): array
    {
        $channelId = (int) Tenant::currentId();
        $zoneIds = $this->channels->zoneIds($channelId);
        $row = RetailerProfile::query()->whereKey($retailerId)->first();
        if ($row === null || $row->zone_id === null || ! in_array((int) $row->zone_id, $zoneIds, true)) {
            throw new DomainException(__('identity.not_found'), 'not_found', 404);
        }

        $phone = null;
        $ownerId = $row->getAttribute('app_user_id');
        if ($ownerId !== null) {
            $raw = AppUser::query()->whereKey((int) $ownerId)->value('phone');
            $phone = is_string($raw) && $raw !== '' ? $raw : null;
        }

        $status = $row->getAttribute('status');
        $zoneId = (int) $row->getAttribute('zone_id');

        return [
            'id' => (int) $row->getKey(),
            'shop_name' => (string) $row->getAttribute('shop_name'),
            'phone' => $phone,
            'zone_id' => $zoneId,
            'activity_type_id' => $row->getAttribute('activity_type_id') !== null
                ? (int) $row->getAttribute('activity_type_id')
                : null,
            'status' => is_object($status) && property_exists($status, 'value')
                ? (string) $status->value
                : (string) $status,
            'credit' => $this->credit->forRetailer($retailerId, $channelId),
            'outstanding' => $this->credit->outstanding($retailerId, $channelId),
            'groups' => $this->groupsForRetailer($retailerId, $channelId),
            'assigned_rep_ids' => $this->assignedRepIds($zoneId, $channelId),
            'last_order_at' => $this->orders->lastOrderAtForRetailerInChannel($retailerId, $channelId),
            'recent_orders' => $this->orders->recentForRetailerInChannel($retailerId, $channelId),
            'top_products' => $this->orders->topProductsForRetailerInChannel($retailerId, $channelId),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function groupsForRetailer(int $retailerId, int $channelId): array
    {
        $groupIds = RetailerGroupMember::query()
            ->where('retailer_id', $retailerId)
            ->pluck('retailer_group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($groupIds === []) {
            return [];
        }

        return Tenant::as($channelId, fn () => RetailerGroup::query()
            ->whereKey($groupIds)
            ->orderBy('id')
            ->get()
            ->map(fn (RetailerGroup $group) => [
                'id' => (int) $group->getKey(),
                'name' => (string) $group->getAttribute('name'),
            ])
            ->all());
    }

    /**
     * Active channel reps whose zone coverage includes this shop's zone.
     *
     * @return list<int>
     */
    private function assignedRepIds(int $zoneId, int $channelId): array
    {
        return RepProfile::query()
            ->where('channel_id', $channelId)
            ->where('status', ProfileStatus::Active)
            ->whereHas('zones', fn ($q) => $q->where('zone_id', $zoneId))
            ->orderBy('app_user_id')
            ->pluck('app_user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
