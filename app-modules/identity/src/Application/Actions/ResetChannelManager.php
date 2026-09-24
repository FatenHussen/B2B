<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Str;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Events\ChannelManagerInvited;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\ChannelManagerInvite;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\ChannelUserChannel;

/**
 * PA-05 — EP-AD-064.
 */
final class ResetChannelManager
{
    public function __construct(
        private readonly RecordsAudit $audit,
        private readonly ChannelDirectory $channels,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{invite_id: int, expires_at: string}
     */
    public function __invoke(int $channelId, array $data, object $actor): array
    {
        if (! $this->channels->exists($channelId)) {
            throw DomainException::of(ErrorCode::NotFound, __('core.not_found'));
        }

        ChannelManagerInvite::query()
            ->where('channel_id', $channelId)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $managerIds = ChannelUserChannel::query()
            ->where('channel_id', $channelId)
            ->pluck('channel_user_id')
            ->all();

        $managers = ChannelUser::query()
            ->whereIn('id', $managerIds === [] ? [0] : $managerIds)
            ->role('channel_manager')
            ->get();

        foreach ($managers as $manager) {
            $manager->tokens()->delete();
        }

        $inviteVia = (string) ($data['invite_via'] ?? 'whatsapp');
        $primary = $managers->first();

        if ($primary !== null) {
            event(new ChannelManagerInvited(
                channelId: $channelId,
                name: (string) $primary->name,
                phone: (string) $primary->phone,
                email: $primary->email,
                inviteVia: $inviteVia,
            ));

            $invite = ChannelManagerInvite::query()
                ->where('channel_id', $channelId)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->orderByDesc('id')
                ->first();

            if ($invite === null) {
                throw DomainException::of(ErrorCode::NotFound, __('core.not_found'));
            }

            $invite->forceFill([
                'created_by' => method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null,
                'reason' => (string) $data['reason'],
            ])->save();

            $this->audit->record('channel.manager.reset', $actor, ChannelManagerInvite::class, (int) $invite->id, [
                'channel_id' => $channelId,
                'reason' => (string) $data['reason'],
            ], $channelId);

            return [
                'invite_id' => (int) $invite->id,
                'expires_at' => $invite->expires_at->toIso8601String(),
            ];
        }

        $expires = now()->addHours(72);
        $invite = ChannelManagerInvite::query()->create([
            'channel_id' => $channelId,
            'token_hash' => hash('sha256', Str::random(40)),
            'invite_via' => $inviteVia,
            'expires_at' => $expires,
            'created_by' => method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null,
            'reason' => (string) $data['reason'],
        ]);

        $this->audit->record('channel.manager.reset', $actor, ChannelManagerInvite::class, (int) $invite->id, [
            'channel_id' => $channelId,
            'reason' => (string) $data['reason'],
        ], $channelId);

        return [
            'invite_id' => (int) $invite->id,
            'expires_at' => $expires->toIso8601String(),
        ];
    }
}
