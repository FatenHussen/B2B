<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Identity\Domain\Enums\OtpChannelUsed;

final class ResendOtpRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'otp_id' => ['required', 'string'],
            'prefer_channel' => ['required', 'string', Rule::in([OtpChannelUsed::Whatsapp->value, OtpChannelUsed::Sms->value])],
        ];
    }
}
