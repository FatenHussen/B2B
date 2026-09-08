<?php

namespace Modules\Reference\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reference\Domain\Models\Currency;

/**
 * @mixin Currency
 */
class CurrencyResource extends JsonResource
{
    /**
     * The shape EP-AD-039A specifies: id, iso, name, decimals, is_display_currency.
     *
     * `is_base` is deliberately absent. It appears in no catalog response, and exposing
     * it would invite a client to treat it as the display currency — the confusion this
     * ticket exists to end. `decimals` governs presentation everywhere; the stored amount
     * stays an integer in the smallest unit regardless of it (rule 7).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iso' => $this->iso,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'decimals' => $this->decimals,
            'is_display_currency' => (bool) $this->is_display_currency,
            'status' => $this->status?->value,
        ];
    }
}
