<?php

declare(strict_types=1);

namespace Modules\Sync\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class PushSyncRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operations' => ['required', 'array', 'min:1', 'max:500'],
            'operations.*.client_op_id' => ['required', 'string', 'max:80'],
            'operations.*.type' => ['required', 'string', 'max:64'],
            'operations.*.payload' => ['required', 'array'],
            'operations.*.created_at' => ['nullable', 'date'],
        ];
    }
}
