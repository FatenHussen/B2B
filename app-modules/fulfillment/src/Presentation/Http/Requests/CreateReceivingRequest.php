<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class CreateReceivingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['purchase', 'transfer', 'field_return'])],
            'reference_no' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.variant_id' => ['nullable', 'integer'],
            'lines.*.qty_expected' => ['required', 'integer', 'min:0'],
            'lines.*.qty_received' => ['required', 'integer', 'min:0'],
            'lines.*.lot_no' => ['nullable', 'string'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'lines.*.location_id' => ['nullable', 'integer'],
        ];
    }
}
