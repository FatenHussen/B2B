<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Tenancy\Domain\Enums\ChannelApplicationStatus;
use Modules\Tenancy\Domain\Models\ChannelApplication;

/**
 * The one place a channel application's status changes (rule 8; PA-04 / EP-AD-061).
 *
 * `under_review → provisioning | rejected`, and nothing else — the catalog names
 * `provisioning` as terminal for the application: from there the channel itself
 * carries the state (`RunProvisioning` moves it to `active`), and the application
 * keeps `channel_id` as the pointer to that outcome.
 *
 * The decision runs in one transaction on a row lock. Two approvals arriving together
 * — a double click, a retry without an idempotency key — both passed the status check
 * when it read the caller's in-memory model, and each created a channel for the same
 * application; and a provisioning that succeeded before the application row failed to
 * save left an orphan channel behind an application still `under_review`. Re-reading
 * the row under `lockForUpdate()` serialises the first case, and the transaction ties
 * the channel to the decision in the second: the second caller sees `provisioning` and
 * gets 409, the failed save rolls the channel back with it.
 */
final class ChannelApplicationLifecycle
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * Decide an application. `$provision` runs inside the lock for the approved branch
     * and returns the id of the channel it created; it is never called on rejection.
     *
     * @param  null|callable(ChannelApplication): int  $provision
     *
     * @throws DomainException 409 `illegal_transition` when the application is already decided
     */
    public function decide(
        ChannelApplication $application,
        ChannelApplicationStatus $to,
        object $actor,
        string $reason,
        ?callable $provision = null,
    ): ChannelApplication {
        if ($to === ChannelApplicationStatus::UnderReview) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('tenancy.illegal_transition'));
        }

        $actorId = method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null;

        return DB::transaction(function () use ($application, $to, $actor, $actorId, $reason, $provision): ChannelApplication {
            /** @var ChannelApplication $locked */
            $locked = ChannelApplication::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ChannelApplicationStatus::UnderReview) {
                throw DomainException::of(ErrorCode::IllegalTransition, __('tenancy.illegal_transition'));
            }

            $channelId = null;
            if ($to === ChannelApplicationStatus::Provisioning) {
                if ($provision === null) {
                    throw DomainException::of(ErrorCode::IllegalTransition, __('tenancy.illegal_transition'));
                }

                $channelId = $provision($locked);
            }

            $locked->status = $to;
            $locked->fill([
                'decided_by' => $actorId,
                'decided_at' => now(),
                'reason' => $reason,
                'channel_id' => $channelId,
            ]);
            $locked->save();

            $this->audit->record(
                $to === ChannelApplicationStatus::Provisioning ? 'channel_application.approved' : 'channel_application.rejected',
                $actor,
                ChannelApplication::class,
                (int) $locked->id,
                array_filter([
                    'reason' => $reason,
                    'channel_id' => $channelId,
                ], static fn ($v): bool => $v !== null),
            );

            return $locked;
        });
    }
}
