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
        // EP-AD-030: `name`, `order`, `status`, `zones_count`. `name` is the Arabic
        // name — the platform dashboard is Arabic — and both spellings stay beside it so
        // the AD-41 client keeps reading every field it already reads.
        return [
            'id' => $this->id,
            'name' => $this->name_ar,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'code' => $this->code,
            'status' => $this->status->value,
            'order' => $this->order,
            'zones_count' => $this->whenCounted('zones'),
        ];
    }
}
