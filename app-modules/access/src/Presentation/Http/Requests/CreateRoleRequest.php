<?php

declare(strict_types=1);

namespace Modules\Access\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class CreateRoleRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'key' => ['required', 'string', 'max:64', 'alpha_dash'],
            'system' => ['required', 'in:platform,channel,warehouse,app'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:96'],
            'copy_from_role_id' => ['nullable', 'integer'],
        ];
    }
}
