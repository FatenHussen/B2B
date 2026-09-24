<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Fulfillment\Application\Actions\PushWarehouseSyncOperations;

final class PushWarehouseSyncRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operations' => ['required', 'array', 'min:1', 'max:500'],
            'operations.*.client_op_id' => ['required', 'string', 'max:80'],
            'operations.*.type' => ['required', 'string', Rule::in(PushWarehouseSyncOperations::TYPES)],
            'operations.*.payload' => ['required', 'array'],
            'operations.*.created_at' => ['nullable', 'date'],
        ];
    }
}
