<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpsertChannelZoneRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['delivery_fee', 'min_order_value'] as $field) {
            if ($this->exists($field) && is_numeric($this->input($field))) {
                $this->merge([$field => number_format((float) $this->input($field), 2, '.', '')]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'delivery_days' => ['nullable', 'array'],
            'delivery_days.*' => ['string', 'in:sun,mon,tue,wed,thu,fri,sat'],
            'delivery_windows' => ['nullable', 'array'],
            'delivery_windows.*.day' => ['required', 'string', 'in:sun,mon,tue,wed,thu,fri,sat'],
            'delivery_windows.*.start' => ['required', 'date_format:H:i'],
            'delivery_windows.*.end' => ['required', 'date_format:H:i'],
            'delivery_fee' => ['nullable', 'decimal:0,2', 'min:0'],
            'min_order_value' => ['nullable', 'decimal:0,2', 'min:0'],
        ];
    }
}
