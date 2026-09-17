<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Identity\Application\Services\RepProfileLifecycle;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;

final class DisableChannelRep
{
    public function __construct(private readonly RepProfileLifecycle $lifecycle) {}

    /**
     * @param  array{reason: string}  $data
     * @return array{id: int, status: string}
     */
    public function __invoke(object $actor, int $repUserId, array $data): array
    {
        $profile = $this->profileOrFail($repUserId);
        $this->lifecycle->transition(
            $profile,
            ProfileStatus::Disabled,
            $actor,
            (string) $data['reason'],
        );

        $user = AppUser::query()->whereKey($repUserId)->first();
        if ($user !== null) {
            $user->forceFill(['status' => UserStatus::Suspended])->save();
        }

        return ['id' => $repUserId, 'status' => ProfileStatus::Disabled->value];
    }

    private function profileOrFail(int $repUserId): RepProfile
    {
        $channelId = (int) Tenant::currentId();
        if (! AppUser::query()->whereKey($repUserId)->where('kind', AppUserKind::Rep)->exists()) {
            throw DomainException::of(ErrorCode::NotFound, __('identity.not_found'));
        }

        $profile = RepProfile::query()
            ->where('app_user_id', $repUserId)
            ->where('channel_id', $channelId)
            ->first();
        if ($profile === null) {
            throw DomainException::of(ErrorCode::NotFound, __('identity.not_found'));
        }

        return $profile;
    }
}
