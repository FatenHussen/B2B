<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class UpdateGovernorateRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['sometimes', 'string', 'max:191'],
            'name_en' => ['sometimes', 'string', 'max:191'],
            'code' => [
                'sometimes', 'string', 'max:16',
                Rule::unique('governorates', 'code')->ignore($this->route('governorate')),
            ],
        ];
    }
}
