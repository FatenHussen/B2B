<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class RegisterRetailerRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:120'],
            'shop_name' => ['required', 'string', 'max:160'],
            'activity_type_id' => ['required', 'integer'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer'],
            'equipment_ids' => ['nullable', 'array'],
            'equipment_ids.*' => ['integer'],
            'governorate_id' => ['required', 'integer'],
            'zone_id' => ['required', 'integer'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
