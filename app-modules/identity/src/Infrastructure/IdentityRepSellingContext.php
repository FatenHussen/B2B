<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure;

use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Models\AppUser;

final class IdentityRepSellingContext implements RepSellingContext
{
    public function for(object $user): array
    {
        if (! $user instanceof AppUser || $user->kind !== AppUserKind::Rep) {
            throw new DomainException(__('auth.forbidden'), 'insufficient_permission', 403);
        }

        $profile = $user->repProfile;
        if ($profile === null) {
            throw new DomainException(__('identity.profile_incomplete'), 'profile_incomplete', 403);
        }

        $zoneIds = $profile->zoneIds();

        return [
            'rep_id' => (int) $profile->id,
            'channel_ids' => [(int) $profile->channel_id],
            'default_zone_id' => $zoneIds[0] ?? null,
            'activity_type_id' => (int) $profile->activity_type_id,
        ];
    }
}
