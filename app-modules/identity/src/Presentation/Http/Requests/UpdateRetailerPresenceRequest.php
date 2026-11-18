<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateRetailerPresenceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'is_online' => ['required', 'boolean'],
        ];
    }
}
