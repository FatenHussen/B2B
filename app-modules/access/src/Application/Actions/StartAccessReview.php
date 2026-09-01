<?php

declare(strict_types=1);

namespace Modules\Access\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Access\Domain\Enums\ReviewStatus;
use Modules\Access\Domain\Models\AccessReview;
use Modules\Access\Domain\Models\AccessReviewItem;
use Modules\Core\Contracts\RecordsAudit;

final class StartAccessReview
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array{quarter: string, scope: string}  $data
     * @return array{campaign_id: int, items_count: int}
     */
    public function __invoke(object $actor, array $data): array
    {
        $review = AccessReview::query()->create([
            'quarter' => $data['quarter'],
            'scope' => $data['scope'],
            'status' => ReviewStatus::Open,
            'started_by' => (int) $actor->getAuthIdentifier(),
        ]);

        $rows = DB::table('model_has_roles')
            ->select('model_id', 'role_id')
            ->distinct()
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            AccessReviewItem::query()->create([
                'access_review_id' => $review->id,
                'user_id' => (int) $row->model_id,
                'role_id' => (int) $row->role_id,
                'suggestion' => 'revoke_unused',
            ]);
            $count++;
        }

        $this->audit->record(
            'access.review.start',
            $actor,
            'access_review',
            (int) $review->id,
            ['after' => ['quarter' => $data['quarter'], 'items' => $count]],
        );

        return [
            'campaign_id' => (int) $review->id,
            'items_count' => $count,
        ];
    }
}
