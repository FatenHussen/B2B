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
 * @property string|null $image
 * @property int $order
 * @property RefStatus $status
 */
class RootCategory extends Model
{
    /** `status` is absent on purpose (rule 8): EP-AD-043C is the only way it moves. */
    protected $fillable = ['name', 'icon', 'image', 'order'];

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
     * @return BelongsToMany<ActivityType, $this>
     */
    public function activityTypes(): BelongsToMany
    {
        return $this->belongsToMany(ActivityType::class, 'activity_type_root_category');
    }
}
