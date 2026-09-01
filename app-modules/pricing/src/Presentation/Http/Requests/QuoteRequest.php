<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class QuoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'min:1'],
            'lines.*.variant_id' => ['nullable', 'integer'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['prohibited'],
            'zone_id' => ['required', 'integer', 'min:1'],
            'unit_price' => ['prohibited'],
            'retailer_id' => ['prohibited'],
        ];
    }
}
