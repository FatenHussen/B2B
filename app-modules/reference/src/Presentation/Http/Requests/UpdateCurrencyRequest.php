<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateCurrencyRequest extends ApiFormRequest
{
    /**
     * EP-AD-042G: {name, decimals, is_display_currency, reason}.
     *
     * `reason` is required — every ref change carries one and is audited before and
     * after. `iso` is absent: an ISO 4217 code is the currency's identity, and renaming
     * it would silently redenominate every amount already stored against the row.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'symbol' => ['sometimes', 'nullable', 'string', 'max:8'],
            'decimals' => ['sometimes', 'integer', 'min:0', 'max:4'],
            'is_display_currency' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
