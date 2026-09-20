<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Support;

use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Identity\Domain\Models\RetailerProfile;

final class RepShopCard
{
    /**
     * @return array<string, mixed>
     */
    public static function from(RetailerProfile $profile, ReferenceDirectory $refs): array
    {
        $profile->loadMissing('user');

        return [
            'id' => (int) $profile->id,
            'shop_name' => (string) $profile->shop_name,
            'logo' => null,
            'zone_id' => (int) $profile->zone_id,
            'zone' => $refs->zoneName((int) $profile->zone_id),
            'address' => $profile->address,
            'phone' => $profile->user?->phone,
            'lat' => $profile->lat,
            'lng' => $profile->lng,
            'is_open' => true,
            'is_active' => $profile->status->value === 'active',
            'last_order_at' => null,
        ];
    }
}
