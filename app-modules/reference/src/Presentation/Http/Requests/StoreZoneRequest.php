<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StoreZoneRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'name' => ['required', 'string', 'max:191'],
            'district' => ['nullable', 'string', 'max:191'],
            'polygon' => ['nullable', 'array'],
            'order' => ['nullable', 'integer', 'min:0'],
            // `status` is gone: EP-AD-033's body does not carry it, and a zone's status
            // moves only through EP-AD-034, which requires a reason.
        ];
    }
}
