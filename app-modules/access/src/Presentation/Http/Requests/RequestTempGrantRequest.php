<?php

declare(strict_types=1);

namespace Modules\Access\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class RequestTempGrantRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'permission' => ['required', 'string', 'max:96'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'reason' => ['required', 'string', 'min:20', 'max:500'],
        ];
    }
}
