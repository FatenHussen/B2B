<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class SubmitRepCartRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
