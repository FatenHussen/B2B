<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class DeviceLoginRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_token' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ];
    }
}
