<?php

declare(strict_types=1);

namespace Modules\Returns\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class CreateReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'sub_order_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in(['return', 'exchange'])],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'integer', 'min:1'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.reason' => ['required', 'string', 'max:255'],
            'lines.*.photos' => ['nullable', 'array'],
        ];
    }
}
