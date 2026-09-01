<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Identity\Domain\Enums\OtpPurpose;
use Modules\Identity\Presentation\Http\Concerns\NormalizesSyrianPhone;
use Modules\Identity\Presentation\Http\Rules\SyrianPhone;

final class RequestPublicOtpRequest extends ApiFormRequest
{
    use NormalizesSyrianPhone;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new SyrianPhone],
            'purpose' => ['required', 'string', Rule::in([OtpPurpose::Login->value, OtpPurpose::Register->value])],
            'client' => ['nullable', 'string', 'max:64'],
        ];
    }
}
