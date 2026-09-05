<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ReplaceProductPricingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:simple,tiered'],
            'base_price' => ['required', 'integer', 'min:0'],
            'currency_id' => ['nullable', 'integer'],
            'tiers' => ['nullable', 'array'],
            'tiers.*.from' => ['required', 'integer', 'min:1'],
            'tiers.*.to' => ['nullable', 'integer'],
            'tiers.*.price' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
