<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class UpdateSupplyChannelRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'slug' => [
                'sometimes', 'string', 'max:191', 'alpha_dash',
                Rule::unique('supply_channels', 'slug')->ignore($this->route('supply_channel')),
            ],
            'legal_name' => ['nullable', 'string', 'max:191'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            // Rule 8: until BE-T01 this accepted `active|suspended`, so a rename could
            // suspend a channel with no reason and no record. Status moves only through
            // ChannelLifecycle — EP-AD-054, BE-T13. `prohibited` so a client still
            // sending it is told, instead of receiving 200 for a status that did not move.
            'status' => ['prohibited'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
