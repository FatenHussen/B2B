<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Contracts\VerifiesPlatformStepUpOtp;

final class RequestPlatformStepUpOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purpose' => [
                'required',
                'string',
                Rule::in([VerifiesPlatformStepUpOtp::PURPOSE_CHANNEL_DELETE]),
            ],
        ];
    }
}
