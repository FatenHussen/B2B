<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Reference\Domain\Enums\RefStatus;

/**
 * @property int $id
 * @property string $name
 * @property string|null $icon
 * @property string|null $description
 * @property int $order
 * @property RefStatus $status
 */
class ActivityType extends Model
{
    /**
     * `status` is absent on purpose (rule 8): it moves only through EP-AD-043B, which
     * demands a reason and reports what the change affects.
     */
    protected $fillable = ['name', 'icon', 'description', 'order'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => RefStatus::class,
            'order' => 'integer',
        ];
    }

    /**
     * The root categories a retailer of this activity is shown first (BE-R04).
     *
     * @return BelongsToMany<RootCategory, $this>
     */
    public function suggestedCategories(): BelongsToMany
    {
        return $this->belongsToMany(RootCategory::class, 'activity_type_root_category');
    }
}
