<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class EditSubOrderLinesRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.line_id' => ['required', 'integer'],
            'changes.*.qty' => ['required', 'integer', 'min:0'],
            'changes.*.removed' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
