<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateZoneRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'governorate_id' => ['sometimes', 'integer', 'exists:governorates,id'],
            'name' => ['sometimes', 'string', 'max:191'],
            'district' => ['sometimes', 'nullable', 'string', 'max:191'],
            'polygon' => ['nullable', 'array'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'reason' => ['sometimes', 'string', 'max:500'],
            // `status` is gone: EP-AD-042B's body does not carry it, and a zone's status
            // moves only through EP-AD-034, which requires a reason.
        ];
    }
}
