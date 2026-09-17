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
     * The three usage counts EP-AD-032 shows per row, when the controller attached them.
     *
     * @var array{retailers_count: int, reps_count: int, channels_count: int}|null
     */
    public ?array $usage = null;

    /**
     * @param  array{retailers_count: int, reps_count: int, channels_count: int}  $usage
     */
    public function withUsage(array $usage): self
    {
        $this->usage = $usage;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'governorate_id' => $this->governorate_id,
            'governorate' => $this->whenLoaded('governorate', fn () => [
                'id' => $this->governorate->id,
                'name' => $this->governorate->name_ar,
            ]),
            'name' => $this->name,
            'district' => $this->district,
            'polygon' => $this->polygon,
            'order' => $this->order,
            // `toContract()`, not `->value`: the column stores `inactive` and the contract
            // says `disabled`. The translation lives here and in the status request, and
            // nowhere else — see ZoneStatus.
            'status' => $this->status?->toContract(),
            'retailers_count' => $this->when($this->usage !== null, fn () => $this->usage['retailers_count']),
            'reps_count' => $this->when($this->usage !== null, fn () => $this->usage['reps_count']),
            'channels_count' => $this->when($this->usage !== null, fn () => $this->usage['channels_count']),
        ];
    }
}
