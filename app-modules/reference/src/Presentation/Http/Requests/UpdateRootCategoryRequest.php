<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

/** EP-AD-042D. `reason` is required — BE-R12. */
final class UpdateRootCategoryRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'activity_type_ids' => ['sometimes', 'array'],
            'activity_type_ids.*' => ['integer', 'exists:activity_types,id'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
