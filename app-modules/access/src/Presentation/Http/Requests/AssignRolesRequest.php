<?php

declare(strict_types=1);

namespace Modules\Access\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class AssignRolesRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:50'],
            'user_ids.*' => ['integer'],
            'role_id' => ['required', 'integer'],
            'expires_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
