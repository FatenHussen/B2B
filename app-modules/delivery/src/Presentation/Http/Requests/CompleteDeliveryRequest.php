<?php

declare(strict_types=1);

namespace Modules\Delivery\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class CompleteDeliveryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required', 'integer'],
            'lines.*.qty_delivered' => ['nullable', 'integer'],
            'lines.*.qty_received' => ['nullable', 'integer'],
            'lines.*.action' => ['nullable', 'string'],
            'delivered_at' => ['nullable', 'date'],
            'signature' => ['nullable', 'string'],
        ];
    }
}
