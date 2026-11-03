<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class AllocateStockShortageRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'qty_available' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', Rule::in(['out_of_stock', 'damaged', 'not_in_location'])],
        ];
    }
}
