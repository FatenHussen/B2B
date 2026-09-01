<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Identity\Application\Services\TokenIssuer;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RepProfileZone;

final class RegisterRep
{
    public function __construct(
        private readonly ChannelDirectory $channels,
        private readonly ReferenceDirectory $refs,
        private readonly TokenIssuer $tokens,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function __invoke(AppUser $user, array $data): array
    {
        $channelId = (int) $data['supply_channel_id'];

        if (! $this->channels->isActive($channelId)) {
            throw new DomainException(__('identity.channel_not_active'), 'conflict', 409);
        }

        $zoneIds = array_map('intval', $data['zone_ids'] ?? []);

        if (! $this->channels->coversAllZones($channelId, $zoneIds)) {
            InvalidFields::throw(['zone_ids' => 'identity.zone_outside_coverage']);
        }

        if (! $this->refs->activityTypeExists((int) $data['activity_type_id'])) {
            InvalidFields::throw(['activity_type_id' => 'identity.activity_type_not_found']);
        }

        if ($user->kind !== null && $user->kind !== AppUserKind::Rep) {
            InvalidFields::throw(['phone' => 'identity.phone_kind_conflict']);
        }

        $profile = DB::transaction(function () use ($user, $data, $channelId, $zoneIds): RepProfile {
            $user->forceFill([
                'name' => $data['name'],
                'kind' => AppUserKind::Rep,
                'status' => UserStatus::Pending,
            ])->save();

            $profile = RepProfile::query()->updateOrCreate(
                ['app_user_id' => $user->id],
                [
                    'channel_id' => $channelId,
                    'activity_type_id' => $data['activity_type_id'],
                    'status' => ProfileStatus::PendingReview,
                    'note' => $data['note'] ?? null,
                ],
            );

            $profile->zones()->delete();
            foreach ($zoneIds as $zoneId) {
                RepProfileZone::query()->create([
                    'rep_profile_id' => $profile->id,
                    'zone_id' => $zoneId,
                ]);
            }

            return $profile;
        });

        $user->tokens()->delete();
        $token = $this->tokens->issue($user, ['*']);

        return [
            'rep' => [
                'id' => $user->id,
                'name' => $user->name,
                'channel' => ['id' => $channelId, 'name' => $this->channels->name($channelId)],
                'zones' => array_map(fn (int $id) => ['id' => $id, 'name' => null], $zoneIds),
                'status' => $profile->status->value,
            ],
            'token' => $token,
        ];
    }
}
