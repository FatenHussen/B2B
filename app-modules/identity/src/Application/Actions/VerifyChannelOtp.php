<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Modules\Core\Contracts\AccessCatalog;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Application\Services\OtpService;
use Modules\Identity\Application\Services\TokenIssuer;
use Modules\Identity\Domain\Models\ChannelUser;

final class VerifyChannelOtp
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly TokenIssuer $tokens,
        private readonly AccessCatalog $access,
        private readonly ChannelDirectory $channels,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $otpId, string $code): array
    {
        $otp = $this->otp->verifyById($otpId, $code);
        $user = ChannelUser::query()->where('phone', $otp->phone)->first();

        if ($user === null) {
            throw new DomainException(__('identity.channel_membership_missing'), 'insufficient_permission', 403);
        }

        $memberships = $user->channelMemberships();

        if ($memberships === []) {
            throw new DomainException(__('identity.channel_membership_missing'), 'insufficient_permission', 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $channels = [];
        foreach ($memberships as $row) {
            $channels[] = [
                'id' => $row['id'],
                'name' => $this->channels->name($row['id']),
            ];
        }

        return [
            'token' => $this->tokens->issue($user, ['*']),
            'channels' => $channels,
            'permissions' => $this->access->permissionsFor($user),
        ];
    }
}
