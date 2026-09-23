<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateVariantRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['sometimes', 'string', 'max:80'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'image' => ['sometimes', 'nullable'],
            'status' => ['sometimes', 'string', 'in:active,disabled'],
            'price_override' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'warehouse_id' => ['sometimes', 'integer', 'min:1'],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
