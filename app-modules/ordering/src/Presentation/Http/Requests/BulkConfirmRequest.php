<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class BulkConfirmRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer'],
        ];
    }
}
