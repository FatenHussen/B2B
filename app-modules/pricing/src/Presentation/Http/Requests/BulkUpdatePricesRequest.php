<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class BulkUpdatePricesRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['integer'],
            'mode' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
