<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Listeners;

use Illuminate\Support\Str;
use Modules\Core\Contracts\AssignsChannelManager;
use Modules\Core\Domain\Events\ChannelManagerInvited;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\ChannelManagerInvite;
use Modules\Identity\Domain\Models\ChannelUser;

/**
 * PA-05 — materialise the channel manager after provisioning (or a reset re-invite).
 *
 * Creates the ChannelUser by phone, assigns membership + role, and records a 72h invite.
 * Login is OTP: VerifyChannelOtp finds the user by phone — no separate accept route in the catalog.
 */
final class ProvisionChannelManagerOnInvite
{
    public function __construct(private readonly AssignsChannelManager $assigns) {}

    public function handle(ChannelManagerInvited $event): void
    {
        $user = ChannelUser::query()->where('phone', $event->phone)->first();
        if ($user === null) {
            $user = ChannelUser::query()->create([
                'name' => $event->name !== '' ? $event->name : $event->phone,
                'phone' => $event->phone,
                'email' => $event->email,
                'status' => UserStatus::Active,
            ]);
        } else {
            $user->fill([
                'name' => $event->name !== '' ? $event->name : $user->name,
                'email' => $event->email ?? $user->email,
            ])->save();
        }

        $this->assigns->assign($event->channelId, (int) $user->id);

        ChannelManagerInvite::query()
            ->where('channel_id', $event->channelId)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        ChannelManagerInvite::query()->create([
            'channel_id' => $event->channelId,
            'token_hash' => hash('sha256', Str::random(40)),
            'invite_via' => $event->inviteVia,
            'expires_at' => now()->addHours(72),
            'reason' => 'provisioning',
        ]);
    }
}
