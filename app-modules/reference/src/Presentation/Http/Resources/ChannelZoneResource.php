<?php

namespace Modules\Reference\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Modules\Reference\Domain\Models\ChannelZone
 */
class ChannelZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zone_id' => $this->zone_id,
            'zone_name' => $this->whenLoaded('zone', fn () => $this->zone->name),
            'delivery_days' => $this->delivery_days,
            'delivery_fee' => $this->delivery_fee,
            'min_order_value' => $this->min_order_value,
        ];
    }
}
