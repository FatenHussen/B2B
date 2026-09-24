<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class CreatePickingWaveRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sub_order_ids' => ['required', 'array', 'min:1'],
            'sub_order_ids.*' => ['integer', 'distinct'],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
