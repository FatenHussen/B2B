<?php

declare(strict_types=1);

namespace Modules\Returns\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class SortReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'integer', 'min:1'],
            'lines.*.condition' => ['required', Rule::in(['resalable', 'damaged', 'expired'])],
        ];
    }
}
