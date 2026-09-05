<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\RetailerDirectory;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RetailerProfile;

final class EloquentRetailerDirectory implements RetailerDirectory
{
    public function find(int $retailerId): ?array
    {
        $profile = RetailerProfile::query()->find($retailerId);

        if ($profile === null) {
            return null;
        }

        return [
            'id' => (int) $profile->getKey(),
            'shop_name' => (string) $profile->getAttribute('shop_name'),
            'address' => $profile->getAttribute('address'),
        ];
    }

    public function exists(int $retailerId): bool
    {
        return RetailerProfile::query()->whereKey($retailerId)->exists();
    }

    public function phone(int $retailerId): ?string
    {
        $ownerId = RetailerProfile::query()->whereKey($retailerId)->value('app_user_id');

        if ($ownerId === null) {
            return null;
        }

        $phone = AppUser::query()->whereKey($ownerId)->value('phone');

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    public function zoneId(int $retailerId): ?int
    {
        $zoneId = RetailerProfile::query()->whereKey($retailerId)->value('zone_id');

        return $zoneId === null ? null : (int) $zoneId;
    }
}
