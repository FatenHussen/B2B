<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StoreCurrencyRequest extends ApiFormRequest
{
    /**
     * EP-AD-039B: {iso, name, decimals, is_display_currency}.
     *
     * `is_base` is not accepted here or anywhere else in the API. `decimals` is required
     * rather than defaulted: a currency silently declared to have zero decimals misreads
     * every amount stored against it, and the column default exists for the migration,
     * not as an answer for a new currency.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'iso' => ['required', 'string', 'size:3', 'unique:currencies,iso'],
            'name' => ['required', 'string', 'max:191'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'is_display_currency' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('iso'))) {
            $this->merge(['iso' => strtoupper($this->input('iso'))]);
        }
    }
}
