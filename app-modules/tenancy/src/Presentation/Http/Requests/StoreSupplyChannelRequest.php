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
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'settings' => ['nullable', 'array'],
        ];
    }
}
