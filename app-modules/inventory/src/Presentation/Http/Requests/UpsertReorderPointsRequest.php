<?php

declare(strict_types=1);

namespace Modules\Inventory\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpsertReorderPointsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.warehouse_id' => ['required', 'integer', 'min:1'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.point' => ['required', 'integer', 'min:0'],
        ];
    }
}
