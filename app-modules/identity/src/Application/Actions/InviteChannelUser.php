<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Str;
use Modules\Core\Contracts\AssignsChannelManager;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\ChannelManagerInvite;
use Modules\Identity\Domain\Models\ChannelUser;

final class InviteChannelUser
{
    public function __construct(
        private readonly AssignsChannelManager $assigns,
    ) {}

    /**
     * @param  array{name: string, phone: string, invite_via?: string}  $data
     * @return array{invite_id: int, expires_at: string}
     */
    public function __invoke(object $actor, array $data): array
    {
        $channelId = (int) Tenant::currentId();
        $phone = (string) $data['phone'];
        $user = ChannelUser::query()->where('phone', $phone)->first();
        if ($user === null) {
            $user = ChannelUser::query()->create([
                'name' => (string) $data['name'],
                'phone' => $phone,
                'status' => UserStatus::Active,
            ]);
        } else {
            $user->fill(['name' => (string) $data['name']])->save();
        }

        $this->assigns->assign($channelId, (int) $user->id);

        $invite = ChannelManagerInvite::query()->create([
            'channel_id' => $channelId,
            'token_hash' => hash('sha256', Str::random(40)),
            'invite_via' => (string) ($data['invite_via'] ?? 'sms'),
            'expires_at' => now()->addHours(72),
            'created_by' => method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null,
            'reason' => 'channel_invite',
        ]);

        return [
            'invite_id' => (int) $invite->id,
            'expires_at' => $invite->expires_at->timezone('Asia/Damascus')->toIso8601String(),
        ];
    }
}
