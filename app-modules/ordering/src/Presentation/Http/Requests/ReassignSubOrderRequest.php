<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ReassignSubOrderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'rep_id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
