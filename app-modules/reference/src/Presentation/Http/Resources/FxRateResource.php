<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reference\Domain\Models\FxRate;

/**
 * @mixin FxRate
 */
final class FxRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // EP-AD-043G speaks of one `currency_id`: the rate is always quoted from that
        // currency into the base, so `from_currency_id` is the contract's `currency_id`
        // and `to_currency_id` is implied. `rate` is the integer at Money::FX_SCALE —
        // never divided here; rounding belongs to MoneyResource alone (rule 7).
        return [
            'id' => $this->id,
            'currency_id' => $this->from_currency_id,
            'base_currency_id' => $this->to_currency_id,
            'rate' => $this->rate,
            'effective_from' => $this->effective_from?->toIso8601String(),
            'effective_to' => $this->effective_to?->toIso8601String(),
            'source' => $this->source,
            'entered_by' => $this->entered_by,
        ];
    }
}
