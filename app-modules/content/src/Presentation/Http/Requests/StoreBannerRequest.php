<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StoreBannerRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media_type' => ['required', 'string', 'in:image,video,gif'],
            'media_id' => ['required', 'string'],
            'link' => ['nullable', 'array'],
            'link.type' => ['nullable', 'string'],
            'link.target' => ['nullable'],
            'placements' => ['required', 'array', 'min:1'],
            'targeting' => ['nullable', 'array'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'order' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
