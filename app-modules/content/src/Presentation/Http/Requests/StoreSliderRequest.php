<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StoreSliderRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'in:manual,brand,category,offers,algorithm'],
            'source_ref' => ['nullable', 'string'],
            'algorithm' => ['nullable', 'string'],
            'placements' => ['nullable', 'array'],
            'items_count' => ['nullable', 'integer', 'min:1'],
            'show_all_button' => ['nullable', 'boolean'],
            'targeting' => ['nullable', 'array'],
        ];
    }
}
