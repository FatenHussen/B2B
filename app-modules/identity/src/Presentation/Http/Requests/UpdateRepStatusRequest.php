<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateRepStatusRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'on_duty' => ['required', 'boolean'],
        ];
    }
}
