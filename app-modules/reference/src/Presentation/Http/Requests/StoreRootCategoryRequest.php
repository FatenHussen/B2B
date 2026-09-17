<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

/** EP-AD-036B. */
final class StoreRootCategoryRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'icon' => ['nullable', 'string', 'max:64'],
            'image' => ['nullable', 'string', 'max:255'],
            'activity_type_ids' => ['nullable', 'array'],
            'activity_type_ids.*' => ['integer', 'exists:activity_types,id'],
            'order' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
