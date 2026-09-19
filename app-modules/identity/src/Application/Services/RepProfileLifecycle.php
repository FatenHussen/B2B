<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;

/**
 * The one place a rep profile's status changes (rule 8 for this model).
 */
final class RepProfileLifecycle
{
    public function __construct(private readonly RecordsAudit $audit) {}

    public function transition(
        RepProfile $profile,
        ProfileStatus $to,
        object $actor,
        ?string $reason = null,
        ?int $channelId = null,
    ): RepProfile {
        $from = $profile->status;
        if (! $this->allowed($from, $to)) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('identity.illegal_rep_transition'));
        }

        $profile->status = $to;
        if ($reason !== null && $reason !== '') {
            $profile->note = $reason;
        }
        $profile->save();

        // A rejected or disabled rep stops working now, not at the next request that
        // happens to check the profile. Every token the rep holds is revoked, so the
        // device in the field is signed out the moment the channel decides.
        if (in_array($to, [ProfileStatus::Rejected, ProfileStatus::Disabled], true)) {
            AppUser::query()->find($profile->app_user_id)?->tokens()->delete();
        }

        $this->audit->record('rep.profile.'.$to->value, $actor, 'rep_profile', (int) $profile->id, [
            'before' => ['status' => $from->value],
            'after' => ['status' => $to->value, 'reason' => $reason],
        ], $channelId ?? (int) $profile->channel_id);

        return $profile;
    }

    private function allowed(ProfileStatus $from, ProfileStatus $to): bool
    {
        return match ($from) {
            ProfileStatus::PendingReview => in_array($to, [ProfileStatus::Active, ProfileStatus::Rejected], true),
            ProfileStatus::Active => $to === ProfileStatus::Disabled,
            default => false,
        };
    }
}
