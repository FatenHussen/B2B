<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

/** EP-AD-042C. `reason` is required — BE-R12. */
final class UpdateActivityTypeRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'suggested_category_ids' => ['sometimes', 'array'],
            'suggested_category_ids.*' => ['integer', 'exists:root_categories,id'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
