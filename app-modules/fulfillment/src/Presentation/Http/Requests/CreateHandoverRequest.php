<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class CreateHandoverRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'rep_id' => ['required', 'integer'],
            'sub_order_ids' => ['required', 'array', 'min:1'],
            'sub_order_ids.*' => ['integer'],
            'rep_qr' => ['nullable', 'string'],
        ];
    }
}
