<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Modules\Core\Support\InvalidFields;
use Modules\Identity\Domain\Models\AppUser;

final class UpdateRepProfile
{
    /**
     * @param  array{name?: string, phone?: string}  $data
     * @return array{name: string, phone: string, avatar: null}
     */
    public function __invoke(AppUser $user, array $data): array
    {
        if (isset($data['phone']) && $data['phone'] !== $user->phone) {
            $taken = AppUser::query()
                ->where('phone', $data['phone'])
                ->whereKeyNot($user->id)
                ->exists();
            if ($taken) {
                InvalidFields::throw(['phone' => 'identity.phone_taken']);
            }
        }

        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
        $user->save();

        return [
            'name' => (string) $user->name,
            'phone' => (string) $user->phone,
            'avatar' => null,
        ];
    }
}
