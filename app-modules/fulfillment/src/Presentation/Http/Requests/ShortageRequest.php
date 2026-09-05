<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class ShortageRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'line_id' => ['required', 'integer'],
            'qty_available' => ['required', 'integer', 'min:0'],
            'reason' => ['required', Rule::in(['out_of_stock', 'damaged', 'not_in_location'])],
        ];
    }
}
