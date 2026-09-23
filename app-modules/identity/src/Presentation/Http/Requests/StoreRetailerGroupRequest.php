<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StoreRetailerGroupRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'retailer_ids' => ['nullable', 'array'],
            'retailer_ids.*' => ['integer', 'min:1'],
        ];
    }
}
