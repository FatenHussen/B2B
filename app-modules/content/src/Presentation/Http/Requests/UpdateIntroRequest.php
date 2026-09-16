<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateIntroRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'text' => ['nullable', 'string'],
            'media_type' => ['nullable', 'string', 'in:image,video'],
            'media_id' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'targeting' => ['nullable', 'array'],
            'targeting.activity_type_ids' => ['nullable', 'array'],
            'targeting.zone_ids' => ['nullable', 'array'],
        ];
    }
}
