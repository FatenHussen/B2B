<?php

declare(strict_types=1);

namespace Modules\Delivery\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class PatchDeliveryLineRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'qty_delivered' => ['nullable', 'integer', 'min:0'],
            'qty_received' => ['nullable', 'integer', 'min:0'],
            'action' => ['required', Rule::in(['accept', 'adjust', 'return', 'exchange'])],
            'reason' => ['nullable', 'string'],
        ];
    }
}
