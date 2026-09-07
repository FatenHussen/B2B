<?php

namespace Modules\Reference\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reference\Domain\Models\Governorate;

/**
 * @mixin Governorate
 */
class GovernorateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // `status` and `order` are added here, not invented: EP-AD-030 lists both in its
        // response shape. This widens the payload rather than changing it, so the AD-41
        // client keeps reading every field it already reads.
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'code' => $this->code,
            'status' => $this->status->value,
            'order' => $this->order,
        ];
    }
}
