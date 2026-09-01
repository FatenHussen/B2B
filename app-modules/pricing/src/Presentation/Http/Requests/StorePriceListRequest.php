<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Pricing\Domain\Enums\PriceListType;

final class StorePriceListRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::enum(PriceListType::class)],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer'],
            'group_id' => ['nullable', 'integer'],
            'retailer_id' => ['nullable', 'integer'],
            'adjustment' => ['required', 'array'],
            'adjustment.mode' => ['required', 'in:percent,fixed'],
            'adjustment.value' => ['required', 'integer'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
