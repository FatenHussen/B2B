<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\RootCategory;

/**
 * @mixin RootCategory
 */
final class RootCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'image' => $this->image,
            'order' => $this->order,
            'status' => $this->status->value,
            'activity_type_ids' => $this->whenLoaded(
                'activityTypes',
                fn () => $this->activityTypes->map(fn (ActivityType $a) => (int) $a->id)->values()->all(),
            ),
        ];
    }
}
