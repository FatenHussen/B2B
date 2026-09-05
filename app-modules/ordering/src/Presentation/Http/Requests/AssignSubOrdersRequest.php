<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class AssignSubOrdersRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'sub_order_ids' => ['required', 'array', 'min:1'],
            'sub_order_ids.*' => ['integer'],
            'rep_id' => ['required', 'integer', 'min:1'],
            'mode' => ['nullable', Rule::in(['manual', 'auto', 'bulk_zone'])],
        ];
    }
}
