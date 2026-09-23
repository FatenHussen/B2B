<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class BulkProductsRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['integer', 'min:1'],
            'action' => ['required', 'in:activate,disable,delete_draft,set_category,set_brand,set_zones,set_status'],
            'payload' => ['nullable', 'array'],
            'payload.category_id' => ['nullable', 'integer', 'min:1'],
            'payload.brand_id' => ['nullable', 'integer', 'min:1'],
            'payload.zone_ids' => ['nullable', 'array'],
            'payload.zone_ids.*' => ['integer', 'min:1'],
            'payload.status' => ['nullable', 'string'],
        ];
    }
}
