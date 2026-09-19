<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class VerifyChannelOtpRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'otp_id' => ['required', 'string'],
            // Same shape as the app flow: unchecked while OTP is bypassed.
            'code' => VerifyPublicOtpRequest::codeRule(),
        ];
    }
}
