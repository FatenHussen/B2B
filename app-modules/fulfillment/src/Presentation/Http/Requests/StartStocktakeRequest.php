<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class StartStocktakeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer'],
            'scope' => ['required', Rule::in(['full', 'partial'])],
        ];
    }
}
