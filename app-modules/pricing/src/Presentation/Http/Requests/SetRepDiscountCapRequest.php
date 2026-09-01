<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class SetRepDiscountCapRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'max_discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'max_cash_hold' => ['required', 'integer', 'min:0'],
        ];
    }
}
