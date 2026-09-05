<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Core\Http\ApiFormRequest;

final class StoreBrandRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:160'],
            'name_en' => ['nullable', 'string', 'max:160'],
            'logo' => ['nullable'],
            'banner' => ['nullable'],
            'description' => ['nullable', 'string'],
            'activity_type_ids' => ['nullable', 'array'],
            'activity_type_ids.*' => ['integer'],
            'order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::enum(BrandStatus::class)],
            'sliders' => ['nullable', 'array'],
            'sliders.*.name' => ['required', 'string'],
            'sliders.*.source' => ['required', 'in:algorithm,manual'],
            'sliders.*.source_id' => ['nullable', 'integer'],
            'sliders.*.count' => ['nullable', 'integer', 'min:0'],
            'sliders.*.order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
