<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateCartSectionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
