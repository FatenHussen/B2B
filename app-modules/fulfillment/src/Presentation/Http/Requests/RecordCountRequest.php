<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class RecordCountRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'counted_qty' => ['required', 'integer', 'min:0'],
        ];
    }
}
