<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Access\Domain\Enums\ReviewItemDecision;

/**
 * @property int $id
 * @property int $access_review_id
 * @property int $user_id
 * @property int $role_id
 * @property string $suggestion
 * @property ReviewItemDecision|null $decision
 * @property string|null $reason
 * @property int|null $decided_by
 * @property \Illuminate\Support\Carbon|null $decided_at
 */
class AccessReviewItem extends Model
{
    protected $fillable = [
        'access_review_id',
        'user_id',
        'role_id',
        'suggestion',
        'decision',
        'reason',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ReviewItemDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AccessReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(AccessReview::class, 'access_review_id');
    }
}
