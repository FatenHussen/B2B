<?php

declare(strict_types=1);

namespace Modules\Returns\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class DecideReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
