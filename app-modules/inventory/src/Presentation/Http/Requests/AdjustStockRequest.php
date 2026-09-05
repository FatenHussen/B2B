<?php

declare(strict_types=1);

namespace Modules\Inventory\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class AdjustStockRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'qty_delta' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
