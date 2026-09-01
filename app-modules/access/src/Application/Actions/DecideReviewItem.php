<?php

declare(strict_types=1);

namespace Modules\Access\Application\Actions;

use Modules\Access\Domain\Enums\ReviewItemDecision;
use Modules\Access\Domain\Enums\ReviewStatus;
use Modules\Access\Domain\Models\AccessReview;
use Modules\Access\Domain\Models\AccessReviewItem;
use Modules\Access\Domain\Models\AccessRole;
use Modules\Access\Infrastructure\GuardUserLocator;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Exceptions\DomainException;
use Spatie\Permission\PermissionRegistrar;

final class DecideReviewItem
{
    public function __construct(
        private readonly GuardUserLocator $users,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{decision: string, reason: string}  $data
     * @return array{item_id: int, decision: string}
     */
    public function __invoke(object $actor, int $reviewId, int $itemId, array $data): array
    {
        $review = AccessReview::query()->find($reviewId);
        if ($review === null) {
            throw new DomainException(__('access.not_found'), 'not_found', 404);
        }

        $item = AccessReviewItem::query()
            ->where('access_review_id', $reviewId)
            ->find($itemId);
        if ($item === null) {
            throw new DomainException(__('access.not_found'), 'not_found', 404);
        }

        $decision = ReviewItemDecision::from($data['decision']);
        $item->decision = $decision;
        $item->reason = $data['reason'];
        $item->decided_by = (int) $actor->getAuthIdentifier();
        $item->decided_at = now();
        $item->save();

        if ($decision === ReviewItemDecision::Revoke) {
            app(PermissionRegistrar::class)->setPermissionsTeamId(0);
            $role = AccessRole::query()->find($item->role_id);
            if ($role !== null) {
                $user = $this->users->find($role->guard_name, (int) $item->user_id);
                if ($user !== null && method_exists($user, 'removeRole')) {
                    $user->removeRole($role);
                }
            }
        }

        $undecided = AccessReviewItem::query()
            ->where('access_review_id', $reviewId)
            ->whereNull('decision')
            ->exists();
        if (! $undecided) {
            $review->status = ReviewStatus::Closed;
            $review->save();
        }

        $this->audit->record(
            'access.review.decide',
            $actor,
            'access_review_item',
            (int) $item->id,
            ['after' => ['decision' => $decision->value], 'reason' => $data['reason']],
        );

        return [
            'item_id' => (int) $item->id,
            'decision' => $decision->value,
        ];
    }
}
