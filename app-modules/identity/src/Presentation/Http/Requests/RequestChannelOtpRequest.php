<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;
use Modules\Identity\Presentation\Http\Concerns\NormalizesSyrianPhone;
use Modules\Identity\Presentation\Http\Rules\SyrianPhone;

final class RequestChannelOtpRequest extends ApiFormRequest
{
    use NormalizesSyrianPhone;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new SyrianPhone],
        ];
    }
}
