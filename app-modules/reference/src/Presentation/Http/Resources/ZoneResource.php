<?php

namespace Modules\Reference\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reference\Domain\Models\Zone;

/**
 * @mixin Zone
 */
class ZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'governorate_id' => $this->governorate_id,
            'name' => $this->name,
            'polygon' => $this->polygon,
            // `toContract()`, not `->value`: the column stores `inactive` and the contract
            // says `disabled`. The translation lives here and in the status request, and
            // nowhere else — see ZoneStatus.
            'status' => $this->status?->toContract(),
        ];
    }
}
