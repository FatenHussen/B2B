<?php

declare(strict_types=1);

namespace Modules\Access\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ApproveRoleRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
