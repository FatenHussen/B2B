<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class StoreSupplyChannelRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191', 'alpha_dash', 'unique:supply_channels,slug'],
            'legal_name' => ['nullable', 'string', 'max:191'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            // Rule 8: status is not a field. A channel is created in its starting state
            // and moves only through ChannelLifecycle. `prohibited` rather than simply
            // dropping the rule: `status` is guarded on the model, so an unlisted field
            // would be ignored silently and the caller would get a 201 for a channel in
            // a state they did not ask for.
            'status' => ['prohibited'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
