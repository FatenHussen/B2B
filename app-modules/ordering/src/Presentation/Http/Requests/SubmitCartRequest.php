<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class SubmitCartRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'sections' => ['nullable', 'array'],
            'sections.*.ref' => ['required', 'string'],
            'sections.*.note' => ['nullable', 'string'],
            'sections.*.scheduled_at' => ['nullable', 'date'],
            'client_created_at' => ['nullable', 'date'],
            'offline_created' => ['nullable', 'boolean'],
        ];
    }
}
