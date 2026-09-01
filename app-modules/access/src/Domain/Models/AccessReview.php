<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Access\Domain\Enums\ReviewStatus;

/**
 * @property int $id
 * @property string $quarter
 * @property string $scope
 * @property ReviewStatus $status
 * @property int $started_by
 */
class AccessReview extends Model
{
    protected $fillable = [
        'quarter',
        'scope',
        'status',
        'started_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
        ];
    }

    /**
     * @return HasMany<AccessReviewItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AccessReviewItem::class);
    }
}
