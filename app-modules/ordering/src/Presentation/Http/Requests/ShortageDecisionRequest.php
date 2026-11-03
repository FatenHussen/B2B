<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

final class ShortageDecisionRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['accept_partial', 'cancel'])],
        ];
    }
}
