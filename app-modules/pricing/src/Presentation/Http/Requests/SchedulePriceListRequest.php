<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class SchedulePriceListRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date'],
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.product_id' => ['required', 'integer'],
            'changes.*.base_price' => ['required', 'integer', 'min:0'],
        ];
    }
}
