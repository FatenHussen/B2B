<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;
use Modules\Identity\Presentation\Http\Concerns\NormalizesSyrianPhone;
use Modules\Identity\Presentation\Http\Rules\SyrianPhone;

final class UpdatePlatformProfileRequest extends ApiFormRequest
{
    use NormalizesSyrianPhone;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', new SyrianPhone],
        ];
    }
}
