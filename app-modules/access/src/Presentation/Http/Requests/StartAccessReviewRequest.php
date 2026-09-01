<?php

declare(strict_types=1);

namespace Modules\Access\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StartAccessReviewRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quarter' => ['required', 'string', 'max:16'],
            'scope' => ['required', 'in:platform,channel,warehouse,app'],
        ];
    }
}
