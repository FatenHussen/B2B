<?php

namespace Modules\Tenancy\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tenancy\Domain\ChannelStateMachine;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * @mixin SupplyChannel
 */
class SupplyChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'legal_name' => $this->legal_name,
            'tax_number' => $this->tax_number,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status->value,
            // The states this channel may move to next, from the matrix and nothing
            // else. The client renders exactly these as buttons (BE-T01): a state absent
            // here — `archived` while `active`, say — has no button, rather than a button
            // that answers 409.
            'allowed_next' => array_map(
                static fn (ChannelStatus $status): string => $status->value,
                app(ChannelStateMachine::class)->allowedNext($this->status),
            ),
            'settings' => $this->settings,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
