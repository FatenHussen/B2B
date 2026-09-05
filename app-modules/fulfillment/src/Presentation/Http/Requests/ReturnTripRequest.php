<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ReturnTripRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'undelivered' => ['nullable', 'array'],
            'undelivered.*.sub_order_id' => ['required', 'integer'],
            'undelivered.*.reason' => ['required', 'string'],
        ];
    }
}
