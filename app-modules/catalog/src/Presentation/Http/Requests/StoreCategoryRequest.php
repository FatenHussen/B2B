<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StoreCategoryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable'],
            'icon' => ['nullable', 'string', 'max:64'],
            'order' => ['nullable', 'integer', 'min:0'],
            'activity_type_ids' => ['nullable', 'array'],
            'activity_type_ids.*' => ['integer'],
        ];
    }
}
