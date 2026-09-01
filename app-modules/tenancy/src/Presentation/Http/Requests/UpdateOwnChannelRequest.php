<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateOwnChannelRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'legal_name' => ['nullable', 'string', 'max:191'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
