<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Models\AppUser;

final class IdentityRetailerShoppingContext implements RetailerShoppingContext
{
    public function __construct(
        private readonly ChannelDirectory $channels,
        private readonly ReferenceDirectory $refs,
    ) {}

    public function isRetailer(object $user): bool
    {
        return $user instanceof AppUser && $user->kind === AppUserKind::Retailer;
    }

    public function for(object $user): array
    {
        if (! $this->isRetailer($user)) {
            throw new DomainException(__('auth.forbidden'), 'insufficient_permission', 403);
        }

        $profile = $user->retailerProfile;
        if ($profile === null) {
            throw new DomainException(__('identity.profile_incomplete'), 'profile_incomplete', 403);
        }

        $categoryIds = $profile->categories()
            ->pluck('root_category_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return [
            'retailer_id' => (int) $profile->id,
            'zone_id' => (int) $profile->zone_id,
            'zone_name' => $this->refs->zoneName((int) $profile->zone_id) ?? '',
            'activity_type_id' => (int) $profile->activity_type_id,
            'category_ids' => $categoryIds,
            'channel_ids' => $this->channels->activeIdsCoveringZone((int) $profile->zone_id),
            'shop_name' => (string) $profile->shop_name,
            'status' => $profile->status->value,
        ];
    }
}
