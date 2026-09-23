<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateRepProfileRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
