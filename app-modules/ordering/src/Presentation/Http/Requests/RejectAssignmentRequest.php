<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class RejectAssignmentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }
}
