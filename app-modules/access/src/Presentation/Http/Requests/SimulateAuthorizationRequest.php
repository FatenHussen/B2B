<?php

declare(strict_types=1);

namespace Modules\Access\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class SimulateAuthorizationRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_type' => ['required', 'in:platform,channel,warehouse,app'],
            'user_id' => ['required', 'integer'],
            'permission' => ['required', 'string', 'max:96'],
            'resource_type' => ['nullable', 'string', 'max:64'],
            'resource_id' => ['nullable', 'integer'],
        ];
    }
}
