<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class CompletePackingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'packages_count' => ['required', 'integer', 'min:1'],
            'total_weight' => ['required'],
            'flags' => ['nullable', 'array'],
        ];
    }
}
