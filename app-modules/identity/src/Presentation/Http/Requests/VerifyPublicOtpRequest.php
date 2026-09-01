<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class VerifyPublicOtpRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'otp_id' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'device_id' => ['required', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:128'],
            'platform' => ['nullable', 'string', 'max:32'],
        ];
    }
}
