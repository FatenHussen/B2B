<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

/**
 * EP-AD-040. `rate` is an integer at `Money::FX_SCALE` — a rate of 1.0 is 1_000_000 —
 * and is validated as an integer so a decimal never reaches the FX path (rule 7, BE-R09).
 */
final class StoreFxRateRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'rate' => ['required', 'integer', 'min:1'],
            'effective_from' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
