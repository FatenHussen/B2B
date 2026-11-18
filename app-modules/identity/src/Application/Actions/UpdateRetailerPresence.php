<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RetailerProfile;

final class UpdateRetailerPresence
{
    /**
     * @return array{is_online: bool}
     */
    public function __invoke(object $user, bool $isOnline): array
    {
        if (! $user instanceof AppUser || $user->kind !== AppUserKind::Retailer) {
            throw new DomainException(__('auth.forbidden'), 'insufficient_permission', 403);
        }

        $profile = RetailerProfile::query()->where('app_user_id', $user->id)->first();
        if ($profile === null) {
            throw new DomainException(__('identity.profile_incomplete'), 'profile_incomplete', 403);
        }

        $profile->is_online = $isOnline;
        $profile->save();

        // Drop the cached relation so a later home call on the same AppUser instance
        // (feature tests keep actingAs across requests) sees the new flag.
        $user->unsetRelation('retailerProfile');

        return ['is_online' => (bool) $profile->is_online];
    }
}
