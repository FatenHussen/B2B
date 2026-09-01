<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Modules\Identity\Domain\Models\PlatformUser;

final class UpdatePlatformProfile
{
    /**
     * @param  array{name: string, phone?: string|null}  $data
     */
    public function __invoke(PlatformUser $user, array $data): void
    {
        $user->forceFill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? $user->phone,
        ])->save();
    }
}
