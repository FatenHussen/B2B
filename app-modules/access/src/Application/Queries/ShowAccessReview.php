<?php

declare(strict_types=1);

namespace Modules\Access\Application\Queries;

use Modules\Access\Domain\Models\AccessReview;
use Modules\Access\Domain\Models\AccessReviewItem;
use Modules\Core\Domain\Exceptions\DomainException;

final class ShowAccessReview
{
    /**
     * @return array{campaign_id: int, quarter: string, status: string, items: list<array{id: int, user_id: int, role_id: int, suggestion: string, decision: string|null}>}
     */
    public function __invoke(int $id): array
    {
        $review = AccessReview::query()->with('items')->find($id);
        if ($review === null) {
            throw new DomainException(__('access.not_found'), 'not_found', 404);
        }

        return [
            'campaign_id' => (int) $review->id,
            'quarter' => $review->quarter,
            'status' => $review->status->value,
            'items' => $review->items->map(fn (AccessReviewItem $item) => [
                'id' => (int) $item->id,
                'user_id' => (int) $item->user_id,
                'role_id' => (int) $item->role_id,
                'suggestion' => $item->suggestion,
                'decision' => $item->decision?->value,
            ])->values()->all(),
        ];
    }
}
