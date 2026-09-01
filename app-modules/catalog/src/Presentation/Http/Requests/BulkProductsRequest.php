<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class BulkProductsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['integer', 'min:1'],
            'action' => ['required', 'in:activate,disable,delete_draft'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
