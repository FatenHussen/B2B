<?php

declare(strict_types=1);

namespace Modules\Delivery\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class RateRepRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'note' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
        ];
    }
}
