<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Identity\Application\Support\ChannelRepPresenter;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;

final class ShowChannelRep
{
    public function __construct(private readonly ChannelRepPresenter $presenter) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(int $repUserId): array
    {
        $channelId = (int) Tenant::currentId();
        $user = AppUser::query()
            ->whereKey($repUserId)
            ->where('kind', AppUserKind::Rep)
            ->first();
        if ($user === null) {
            throw DomainException::of(ErrorCode::NotFound, __('identity.not_found'));
        }

        $profile = RepProfile::query()
            ->where('app_user_id', $repUserId)
            ->where('channel_id', $channelId)
            ->first();
        if ($profile === null) {
            throw DomainException::of(ErrorCode::NotFound, __('identity.not_found'));
        }

        return $this->presenter->present($profile, $user);
    }
}
