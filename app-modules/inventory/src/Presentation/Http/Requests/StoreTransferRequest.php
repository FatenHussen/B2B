<?php

declare(strict_types=1);

namespace Modules\Inventory\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StoreTransferRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'from_warehouse_id' => ['required', 'integer', 'min:1'],
            'to_warehouse_id' => ['required', 'integer', 'min:1'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'min:1'],
            'lines.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
