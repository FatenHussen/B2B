<?php

declare(strict_types=1);

namespace Modules\Returns\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class EscalateReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:255'],
        ];
    }
}
